<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PlatformOperationActivity
{
    public const string QUEUE_WORKER = 'queue_worker';

    public const string SCHEDULER = 'scheduler';

    public const string SMTP = 'email_delivery';

    public const string BACKUP = 'backup_verification';

    private const array SIGNALS = [self::QUEUE_WORKER, self::SCHEDULER, self::SMTP, self::BACKUP];

    private const string KEY_PREFIX = 'medloby:operation-activity:';

    public function record(string $signal): void
    {
        try {
            $connection = DB::connection(config('cache.stores.database.connection'));
            $timestamp = now()->timestamp;
            if ($connection->transactionLevel() > 0) {
                $connection->afterCommit(fn () => $this->write($signal, $timestamp));

                return;
            }
            $this->write($signal, $timestamp);
        } catch (Throwable) {
            // A measurement must not interrupt the successful operation it observes.
        }
    }

    /** @return array<string, string|null> */
    public function lastObserved(): array
    {
        $keys = array_map(fn (string $signal): string => self::KEY_PREFIX.$signal, self::SIGNALS);
        try {
            $values = Cache::store('database')->many($keys);
        } catch (Throwable) {
            $values = [];
        }

        return array_combine(self::SIGNALS, array_map(function (string $key) use ($values): ?string {
            $timestamp = $values[$key] ?? null;

            return is_int($timestamp) && $timestamp > 0
                ? CarbonImmutable::createFromTimestampUTC($timestamp)->toIso8601String()
                : null;
        }, $keys));
    }

    private function write(string $signal, int $timestamp): void
    {
        try {
            $cache = Cache::store('database');
            $key = self::KEY_PREFIX.$signal;
            $cache->lock($key.':write', 5)->get(function () use ($cache, $key, $timestamp): void {
                if ((int) $cache->get($key, 0) < $timestamp) {
                    $cache->forever($key, $timestamp);
                }
            });
        } catch (Throwable) {
            // Failed observation writes can be retried by the next successful operation.
        }
    }
}
