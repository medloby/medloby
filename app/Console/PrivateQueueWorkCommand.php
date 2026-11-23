<?php

namespace App\Console;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\Events\JobFailed;
use RuntimeException;
use Throwable;

final class PrivateQueueWorkCommand extends WorkCommand
{
    /**
     * Give the failure store a string-safe exception while other listeners receive the original.
     */
    protected function logFailedJob(JobFailed $event): void
    {
        $safeEvent = clone $event;
        $safeEvent->exception = new class(get_debug_type($event->exception)) extends RuntimeException
        {
            public function __toString(): string
            {
                return $this->getMessage();
            }
        };
        parent::logFailedJob($safeEvent);
    }

    protected function writeOutputAsJson(Job $job, $status, ?Throwable $exception = null): void
    {
        parent::writeOutputAsJson($job, $status);
    }
}
