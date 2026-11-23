<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use PDO;
use Pdo\Sqlite;
use PDOException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class LocalBackup
{
    private const string PRIVATE_DIRECTORY = 'storage/app/private';

    private const string SQLITE_FILE = 'database.sqlite';

    private const string POSTGRES_FILE = 'database.dump';

    public function __construct(private readonly Filesystem $files) {}

    public function create(string $destination): string
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['sqlite', 'pgsql'], true)) {
            throw new RuntimeException('Yedekleme için dosya tabanlı SQLite veya PostgreSQL gerekir.');
        }
        if (config('filesystems.disks.private.driver') !== 'local') {
            throw new RuntimeException('Yedekleme için private diski yerel bir dizin olmalıdır.');
        }

        $root = $this->privateRoot();
        $disks = ['private'];
        if (config('filesystems.disks.local.driver') === 'local'
            && realpath((string) config('filesystems.disks.local.root')) === $root) {
            $disks[] = 'local';
        }

        return $this->inNewDirectory($destination, function (string $target) use ($connection, $driver, $root, $disks): void {
            $manifest = $driver === 'pgsql'
                ? ['version' => 2, 'driver' => 'pgsql', 'sha256' => $this->snapshotPostgres($connection, $root, $target, $disks)]
                : ['version' => 1, 'sha256' => $this->snapshotSqlite($connection, $root, $target, $disks)];
            $manifest = json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if ($this->files->put($target.'/manifest.json', $manifest.PHP_EOL) === false) {
                throw new RuntimeException('Yedek doğrulama dosyası yazılamadı.');
            }
            $this->protectFile($target.'/manifest.json');
            $this->protectFile($target.'/'.($driver === 'pgsql' ? self::POSTGRES_FILE : self::SQLITE_FILE));
        });
    }

    public function restore(string $backup, string $destination): string
    {
        $source = realpath($backup);
        if ($source === false || ! is_file($source.'/manifest.json') || is_link($source.'/manifest.json')) {
            throw new RuntimeException('Yedek doğrulama dosyası bulunamadı.');
        }
        $manifest = json_decode($this->files->get($source.'/manifest.json'), true);
        $databaseFile = match ([$manifest['version'] ?? null, $manifest['driver'] ?? null]) {
            [1, null] => self::SQLITE_FILE,
            [2, 'pgsql'] => self::POSTGRES_FILE,
            default => null,
        };
        if ($databaseFile === null || ! is_array($manifest['sha256'] ?? null)) {
            throw new RuntimeException('Yedek doğrulama dosyası geçersiz.');
        }
        $expected = $manifest['sha256'];
        ksort($expected);
        $private = $source.'/'.self::PRIVATE_DIRECTORY;
        if (realpath($private) !== $private || ! is_dir($private)) {
            throw new RuntimeException('Yedekteki özel dosya dizini geçersiz.');
        }

        return $this->inNewDirectory($destination, function (string $target) use ($source, $private, $expected, $databaseFile): void {
            $this->copyFile($source.'/'.$databaseFile, $target.'/'.$databaseFile);
            $this->copyPrivateFiles($private, $target.'/'.self::PRIVATE_DIRECTORY);

            $checksums = $this->checksums($target, $databaseFile);
            if ($checksums !== $expected) {
                throw new RuntimeException('Yedekteki dosyalar doğrulama toplamlarıyla eşleşmiyor.');
            }
            if ($databaseFile === self::POSTGRES_FILE) {
                $this->validatePostgresDump($target.'/'.$databaseFile);
            } else {
                $this->validateSqlite($target.'/'.$databaseFile, $checksums, ['private', 'local']);
            }
        }, [$source]);
    }

    /**
     * @param  array<string>  $disks
     * @return array<string, string>
     */
    private function snapshotSqlite(Connection $connection, string $root, string $target, array $disks): array
    {
        $database = $connection->getPdo();
        $databasePath = $database->query("SELECT file FROM pragma_database_list WHERE name = 'main'")->fetchColumn();
        if (! is_string($databasePath) || ! is_file($databasePath)) {
            throw new RuntimeException('Yedekleme için dosya tabanlı bir SQLite veritabanı gerekir.');
        }

        $snapshot = $database->prepare('VACUUM INTO ?');
        $snapshot->execute([$target.'/'.self::SQLITE_FILE]);
        $this->copyPrivateFiles($root, $target.'/'.self::PRIVATE_DIRECTORY);
        $checksums = $this->checksums($target, self::SQLITE_FILE);
        $this->validateSqlite($target.'/'.self::SQLITE_FILE, $checksums, $disks);

        return $checksums;
    }

    /**
     * @param  array<string>  $disks
     * @return array<string, string>
     */
    private function snapshotPostgres(Connection $connection, string $root, string $target, array $disks): array
    {
        $dump = $target.'/'.self::POSTGRES_FILE;
        try {
            $checksums = $connection->transaction(function () use ($connection, $root, $target, $disks, $dump): array {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                $snapshot = $connection->scalar('SELECT pg_export_snapshot()', useReadPdo: false);
                if (! $this->runPostgresTool([
                    'pg_dump', '--format=custom', '--no-password', '--lock-wait-timeout=30s',
                    '--snapshot='.$snapshot, '--file='.$dump,
                ], $this->postgresEnvironment($connection))) {
                    throw new RuntimeException('PostgreSQL yedeği oluşturulamadı. pg_dump sürümünü ve veritabanı erişimini kontrol edin.');
                }

                $this->copyPrivateFiles($root, $target.'/'.self::PRIVATE_DIRECTORY);
                $checksums = $this->checksums($target, self::POSTGRES_FILE);
                $attachments = $connection->table('message_attachments')->select('disk', 'path')->useWritePdo()->cursor();
                $this->validateAttachments($attachments, $checksums, $disks);

                return $checksums;
            });
        } catch (PDOException) {
            throw new RuntimeException('PostgreSQL yedeği için veritabanı okunamadı.');
        }
        $this->validatePostgresDump($dump);

        return $checksums;
    }

    /** @return array<string, string|false> */
    private function postgresEnvironment(Connection $connection): array
    {
        $config = $connection->getConfig();

        return [
            'PGDATABASE' => (string) ($config['connect_via_database'] ?? $config['database']),
            'PGHOST' => implode(',', (array) ($config['host'] ?? [])),
            'PGPORT' => (string) ($config['connect_via_port'] ?? $config['port'] ?? 5432),
            'PGUSER' => (string) $config['username'],
            'PGPASSWORD' => (string) ($config['password'] ?? ''),
            'PGSSLMODE' => (string) ($config['sslmode'] ?? 'prefer'),
            'PGSSLCERT' => $config['sslcert'] ?? false,
            'PGSSLKEY' => $config['sslkey'] ?? false,
            'PGSSLROOTCERT' => $config['sslrootcert'] ?? false,
            'PGCONNECT_TIMEOUT' => '10',
            'PGHOSTADDR' => false,
            'PGSERVICE' => false,
        ];
    }

    private function validatePostgresDump(string $dump): void
    {
        if (! $this->runPostgresTool(['pg_restore', '--exit-on-error', '--file=-', $dump])) {
            throw new RuntimeException('PostgreSQL yedek dosyası okunamadı. pg_restore sürümünü ve yedeğin bütünlüğünü kontrol edin.');
        }
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $environment
     */
    private function runPostgresTool(array $command, array $environment = []): bool
    {
        try {
            return Process::forever()->quietly()->env($environment)->run($command)->successful();
        } catch (RuntimeException) {
            return false;
        }
    }

    private function privateRoot(): string
    {
        $root = config('filesystems.disks.private.root');
        if (! is_string($root) || $root === '') {
            throw new RuntimeException('Özel dosya dizini yapılandırılmalıdır.');
        }

        return realpath($root) ?: $root;
    }

    /**
     * @param  array<string>  $excludedRoots
     */
    private function inNewDirectory(string $destination, Closure $write, array $excludedRoots = []): string
    {
        $parent = realpath(dirname($destination));
        if ($destination === '' || $parent === false || ! is_dir($parent)) {
            throw new RuntimeException('Hedef dizinin üst dizini mevcut olmalıdır.');
        }

        $target = rtrim($parent, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($destination);
        $roots = [public_path(), (string) config('filesystems.disks.public.root'), $this->privateRoot(), ...$excludedRoots];
        foreach (array_filter($roots) as $root) {
            $root = realpath($root) ?: $root;
            $root = rtrim($root, DIRECTORY_SEPARATOR);
            if ($target === $root || str_starts_with($target, $root.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Yedekleme ve geri yükleme için kaynak ve web dizinlerinin dışında bir hedef seçin.');
            }
        }
        if (! $this->files->makeDirectory($target, 0700, force: true)) {
            throw new RuntimeException('Hedef dizin mevcut veya oluşturulamıyor.');
        }

        try {
            $write($target);
        } catch (Throwable $error) {
            $this->files->deleteDirectory($target);

            throw $error;
        }

        return $target;
    }

    private function copyPrivateFiles(string $source, string $target): void
    {
        $this->files->ensureDirectoryExists($target, 0700);
        if (! file_exists($source)) {
            return;
        }

        foreach ($this->privateFiles($source) as $relative => $file) {
            $output = $target.'/'.$relative;
            $this->files->ensureDirectoryExists(dirname($output), 0700);
            $this->copyFile($file, $output);
        }
    }

    private function copyFile(string $source, string $target): void
    {
        if (is_link($source) || ! is_file($source) || ! $this->files->copy($source, $target)) {
            throw new RuntimeException('Yedek dosyası okunamadı veya kopyalanamadı.');
        }
        $this->protectFile($target);
    }

    private function protectFile(string $path): void
    {
        if (PHP_OS_FAMILY !== 'Windows' && ! $this->files->chmod($path, 0600)) {
            throw new RuntimeException('Yedek dosyasının erişim izinleri ayarlanamadı.');
        }
    }

    /**
     * @return iterable<string, string>
     */
    private function privateFiles(string $path): iterable
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($entries as $entry) {
            if ($entry->isLink() || (! $entry->isFile() && ! $entry->isDir())) {
                throw new RuntimeException('Özel dosya dizininde bağlantı veya özel bir dosya türü bulundu.');
            }
            if ($entry->isFile()) {
                yield str_replace(DIRECTORY_SEPARATOR, '/', $entries->getSubPathname()) => $entry->getPathname();
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function checksums(string $target, string $databaseFile): array
    {
        $paths = [$databaseFile => $target.'/'.$databaseFile];
        foreach ($this->privateFiles($target.'/'.self::PRIVATE_DIRECTORY) as $relative => $file) {
            $paths[self::PRIVATE_DIRECTORY.'/'.$relative] = $file;
        }
        ksort($paths);

        return array_map(function (string $file): string {
            return hash_file('sha256', $file) ?: throw new RuntimeException('Yedek dosyasının doğrulama toplamı hesaplanamadı.');
        }, $paths);
    }

    /**
     * @param  array<string, string>  $checksums
     * @param  array<string>  $disks
     */
    private function validateSqlite(string $file, array $checksums, array $disks): void
    {
        $database = new PDO('sqlite:'.$file, options: [
            Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY,
        ]);
        if ($database->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN) !== ['ok']) {
            throw new RuntimeException('SQLite bütünlük kontrolü başarısız.');
        }

        $this->validateAttachments($database->query('SELECT disk, path FROM message_attachments', PDO::FETCH_OBJ), $checksums, $disks);
    }

    /**
     * @param  iterable<object{disk: string, path: string}>  $attachments
     * @param  array<string, string>  $checksums
     * @param  array<string>  $disks
     */
    private function validateAttachments(iterable $attachments, array $checksums, array $disks): void
    {
        foreach ($attachments as $attachment) {
            if (! in_array($attachment->disk, $disks, true) || ! isset($checksums[self::PRIVATE_DIRECTORY.'/'.$attachment->path])) {
                throw new RuntimeException('Yedekte veritabanına bağlı bir görüşme dosyası eksik.');
            }
        }
    }
}
