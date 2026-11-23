<?php

namespace Tests;

use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function withConcurrentDatabase(Closure $test): void
    {
        $directory = sys_get_temp_dir().'/medloby-concurrent-'.Str::uuid();
        File::makeDirectory($directory, 0700);
        $postgres = DB::getDefaultConnection() === 'pgsql' ? DB::connection('pgsql') : null;
        $connection = $postgres ? 'concurrency_test' : 'sqlite';
        $settings = ['database.default' => $connection];
        $schema = null;

        try {
            if ($postgres) {
                $schemaName = 'medloby_race_'.bin2hex(random_bytes(8));
                $postgres->statement('create schema "'.$schemaName.'"');
                $schema = $schemaName;
                $settings['database.connections.concurrency_test'] = [
                    ...$postgres->getConfig(), 'name' => $connection, 'search_path' => $schema,
                ];
            } else {
                File::put($directory.'/test.sqlite', '');
                $settings += ['database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => $directory.'/test.sqlite'];
            }
            config($settings);
            DB::purge($connection);
            if ($schema !== null && DB::scalar('select current_schema()') !== $schema) {
                throw new RuntimeException('The concurrency test did not connect to its temporary schema.');
            }
            $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
            $test($directory, $settings);
        } finally {
            DB::purge($connection);
            if ($schema !== null) {
                $postgres->statement('drop schema "'.$schema.'" cascade');
            }
            File::deleteDirectory($directory);
        }
    }

    protected function concurrentPostRequests(string $directory, array $settings, string $insertedModel, array $first, array $second): array
    {
        $configureWorker = static function () use ($settings): void {
            config($settings + [
                'app.debug' => false, 'cache.default' => 'array', 'logging.default' => 'null',
                'mail.default' => 'array', 'queue.default' => 'sync', 'session.driver' => 'array',
            ]);
            DB::purge($settings['database.default']);
        };
        $waitForFile = static function (string $path, float $seconds): bool {
            $deadline = microtime(true) + $seconds;
            while (! is_file($path) && microtime(true) < $deadline) {
                usleep(10_000);
            }

            return is_file($path);
        };
        $postAs = static function (array $submission): array {
            Carbon::setTestNow(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
            Auth::guard('web')->setUser(User::findOrFail($submission['user_id']));
            $request = Request::create($submission['path'], 'POST', server: [
                'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            ], content: json_encode($submission['payload'], JSON_THROW_ON_ERROR));
            $response = app(Kernel::class)->handle($request);

            return [$response->getStatusCode(), json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)];
        };

        return Concurrency::driver('process')->run([
            'first' => static function () use ($configureWorker, $insertedModel, $directory, $waitForFile, $postAs, $first): array {
                $configureWorker();
                $insertedModel::created(static function () use ($directory, $waitForFile): void {
                    File::put($directory.'/first-inserted', '');
                    if (! $waitForFile($directory.'/second-started', 5)) {
                        throw new RuntimeException('The second transaction did not start.');
                    }
                    // Let the second transaction attempt its write before this insert commits.
                    $waitForFile($directory.'/second-finished', 1);
                });

                return $postAs($first);
            },
            'second' => static function () use ($configureWorker, $directory, $waitForFile, $postAs, $second): array {
                $configureWorker();
                if (! $waitForFile($directory.'/first-inserted', 5)) {
                    throw new RuntimeException('The first request did not insert its record.');
                }
                DB::connection()->beforeExecuting(static function (string $sql, array $bindings, Connection $connection) use ($directory): void {
                    if ($connection->transactionLevel() > 0 && ! is_file($directory.'/second-started')) {
                        File::put($directory.'/second-started', '');
                    }
                });

                try {
                    return $postAs($second);
                } finally {
                    File::put($directory.'/second-finished', '');
                }
            },
        ], timeout: 15);
    }
}
