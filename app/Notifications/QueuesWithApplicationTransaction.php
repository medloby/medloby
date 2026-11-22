<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

trait QueuesWithApplicationTransaction
{
    use Queueable;

    public function withApplicationTransaction(): static
    {
        $queue = Queue::connection($this->connection);
        if ($queue instanceof DatabaseQueue && $queue->getDatabase() === DB::connection()) {
            // The job record commits with the application data on the same database connection.
            return $this->beforeCommit();
        }

        return $this->afterCommit();
    }
}
