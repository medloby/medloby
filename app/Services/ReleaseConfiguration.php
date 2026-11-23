<?php

namespace App\Services;

use Aws\DynamoDb\DynamoDbClient;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ReleaseConfiguration
{
    public function __construct(
        private readonly PlatformOperationActivity $activity,
        private readonly PublicFileExposure $publicFiles,
    ) {}

    public function databaseReady(): bool
    {
        return DB::connection()->selectOne('select 1', useReadPdo: false) !== null;
    }

    public function operationSummary(Migrator $migrator, PrivacyPublication $privacy): array
    {
        $database = $this->readStatus($this->databaseReady(...), 'unavailable');
        $version = config('medloby.release_version');
        $observed = $this->activity->lastObserved();

        return [
            'application' => 'reachable',
            'database' => $database,
            'migrations' => $database === 'ready' ? $this->readStatus(fn (): bool => $this->migrationsReady($migrator), 'needs_attention') : 'unknown',
            'queue_configuration' => $this->readStatus(fn (): bool => $this->queueReady() && $this->failedJobStorageReady(), 'not_configured'),
            'queue_worker' => $observed[PlatformOperationActivity::QUEUE_WORKER] ? 'observed' : 'unknown',
            'scheduler' => $observed[PlatformOperationActivity::SCHEDULER] ? 'observed' : 'unknown',
            'smtp_configuration' => $this->readStatus($this->mailReady(...), 'not_configured'),
            'email_delivery' => $observed[PlatformOperationActivity::SMTP] ? 'observed' : 'unknown',
            'backup_verification' => $observed[PlatformOperationActivity::BACKUP] ? 'observed' : 'unknown',
            'last_observed_at' => $observed,
            'privacy_publication' => $privacy->isPublished() ? 'ready' : 'not_configured',
            'privacy_contact_configuration' => $privacy->contactSettingsReady() ? 'ready' : 'needs_attention',
            'release_version' => is_string($version) && preg_match('/\A(?:[a-f0-9]{7,40}|v?\d+\.\d+\.\d+(?:[-+][a-z0-9.-]{1,35})?)\z/i', $version) ? $version : null,
        ];
    }

    private function readStatus(callable $probe, string $failure): string
    {
        try {
            return $probe() ? 'ready' : $failure;
        } catch (Throwable) {
            return $failure === 'unavailable' ? $failure : 'unknown';
        }
    }

    public function migrationsReady(Migrator $migrator): bool
    {
        if (! $migrator->repositoryExists()) {
            return false;
        }

        $files = $migrator->getMigrationFiles([...$migrator->paths(), database_path('migrations')]);

        return array_diff(array_keys($files), $migrator->getRepository()->getRan()) === [];
    }

    public function queueReady(): bool
    {
        $queue = config('queue.connections.'.config('queue.default'), []);
        $driver = $queue['driver'] ?? null;
        if (! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            return false;
        }

        return $driver !== 'database' || Schema::connection($queue['connection'] ?? config('database.default'))
            ->hasColumns($queue['table'] ?? 'jobs', ['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at']);
    }

    public function failedJobStorageReady(): bool
    {
        $failed = config('queue.failed', []);
        $driver = array_key_exists('driver', $failed) ? $failed['driver'] : 'database';

        return match ($driver) {
            'database-uuids', 'database' => $this->failedJobTableReady($failed, $driver === 'database-uuids'),
            'file' => $this->failedJobFileReady($failed['path'] ?? storage_path('framework/cache/failed-jobs.json'), $failed['limit'] ?? 100),
            'dynamodb' => class_exists(DynamoDbClient::class) && filled($failed['table'] ?? null) && filled($failed['region'] ?? null),
            default => false,
        };
    }

    private function failedJobTableReady(array $failed, bool $usesUuid): bool
    {
        $table = $failed['table'] ?? null;
        if (! is_string($table) || blank($table) || ! array_key_exists('database', $failed)) {
            return false;
        }

        $columns = array_column(Schema::connection($failed['database'])->getColumns($table), null, 'name');
        if (array_diff(['id', 'connection', 'queue', 'payload', 'exception', 'failed_at'], array_keys($columns)) !== []) {
            return false;
        }

        $uuid = $columns['uuid'] ?? null;

        return $usesUuid
            ? $uuid !== null && $uuid['generation'] === null
            : $uuid === null || $uuid['nullable'] || $uuid['default'] !== null || $uuid['generation'] !== null;
    }

    private function failedJobFileReady(mixed $path, mixed $limit): bool
    {
        if (! is_string($path) || blank($path) || filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return false;
        }

        $writable = is_file($path)
            ? is_readable($path) && is_writable($path)
            : ! file_exists($path) && ! is_link($path) && is_dir(dirname($path)) && is_writable(dirname($path));
        $publicRoots = array_filter([public_path(), (string) config('filesystems.disks.public.root')]);

        return $writable && ! $this->publicFiles->exposes($publicRoots, [$path]);
    }

    public function limiterReady(): bool
    {
        $store = config('cache.limiter') ?: config('cache.default');
        $driver = config('cache.stores.'.$store.'.driver');

        return match ($driver) {
            'database' => Schema::connection(config('cache.stores.'.$store.'.connection') ?? config('database.default'))
                ->hasTable(config('cache.stores.'.$store.'.table', 'cache')),
            'file' => is_writable(config('cache.stores.'.$store.'.path', '')),
            'redis', 'memcached', 'dynamodb' => true,
            default => false,
        };
    }

    public function sessionStorageReady(): bool
    {
        $driver = config('session.driver');
        $store = config('session.store') ?: $driver;

        return match ($driver) {
            'cookie' => true,
            'database' => Schema::connection(config('session.connection'))
                ->hasColumns(config('session.table'), ['id', 'payload', 'last_activity', 'user_id', 'ip_address', 'user_agent']),
            'file' => $this->fileSessionsReady(config('session.files')),
            'redis', 'memcached', 'dynamodb', 'apc' => config('cache.stores.'.$store.'.driver') === $driver,
            default => false,
        };
    }

    private function fileSessionsReady(mixed $path): bool
    {
        if (! is_string($path) || ! is_dir($path) || ! is_readable($path) || ! is_writable($path)) {
            return false;
        }

        $publicRoots = array_filter([public_path(), (string) config('filesystems.disks.public.root')]);

        return ! $this->publicFiles->exposes($publicRoots, [$path]);
    }

    public function mailReady(): bool
    {
        $mailer = config('mail.mailers.'.config('mail.default'), []);
        if (($mailer['transport'] ?? null) !== 'smtp' || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (filled($mailer['url'] ?? null)) {
            $mailer = (new ConfigurationUrlParser)->parseConfiguration($mailer);
            if (($mailer['driver'] ?? null) !== 'smtp') {
                return false;
            }
        }

        return in_array($mailer['scheme'] ?? null, [null, 'smtp', 'smtps'], true)
            && filled($mailer['host'] ?? null)
            && filter_var($mailer['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) !== false;
    }
}
