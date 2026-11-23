<?php

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;

uses(RefreshDatabase::class);

final class PrivateQueueFailureFixture implements ShouldBeEncrypted, ShouldQueue
{
    public function handle(): void
    {
        if (! config('queue.fixture_recovered')) {
            throw new QueryException('fixture', 'select ?', ['synthetic-private-binding'], new RuntimeException('synthetic-driver-details', 23000));
        }

        Cache::put('queue-privacy-fixture-completed', true);
    }
}

test('the queue worker protects failure records and output while allowing retry of the original job', function () {
    config([
        'queue.default' => 'database',
        'queue.failed.driver' => 'database-uuids',
        'queue.failed.database' => config('database.default'),
    ]);

    Exceptions::fake();
    Event::listen(JobFailed::class, function (JobFailed $event): void {
        expect($event->exception)->toBeInstanceOf(QueryException::class);
    });

    Queue::connection('database')->push(new PrivateQueueFailureFixture);
    $payload = DB::table('jobs')->value('payload');
    $worker = ['--once' => true, '--sleep' => 0, '--tries' => 1, '--json' => true];

    expect(Artisan::call('queue:work', $worker))->toBe(0);
    expect(Artisan::output())->toContain('"status":"failed"')
        ->not->toContain('synthetic-private-binding', 'synthetic-driver-details');

    $failed = DB::table('failed_jobs')->sole();
    expect($failed->payload)->toBe($payload)->and($failed->exception)->toBe(QueryException::class);
    $this->assertDatabaseCount('jobs', 0);

    config(['queue.fixture_recovered' => true]);
    $this->artisan('queue:retry', ['id' => [$failed->uuid]])->assertSuccessful();
    $this->artisan('queue:work', $worker)->assertSuccessful();
    expect(Cache::get('queue-privacy-fixture-completed'))->toBeTrue();
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
});

test('SMTP transport reports keep relay details out of the application log', function () {
    Log::spy();
    $error = new TransportException('synthetic-private-relay-details');
    $error->appendDebug('synthetic-private-mail-debug');

    report($error);

    Log::shouldHaveReceived('error')->once()->with('E-posta taşıyıcısı işlemi tamamlayamadı.', [
        'exception_type' => TransportException::class,
    ]);
    Log::shouldHaveReceived('error')->once();
});
