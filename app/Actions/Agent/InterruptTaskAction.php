<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Jobs\RunTaskWorkerStep;
use App\Models\AgentTask;

class InterruptTaskAction
{
    public function __construct(
        public readonly AgentTask $task,
        public readonly string $message,
    ) {}

    public function handle(): void
    {
        RunTaskWorkerStep::dispatch($this->task, $this->message);
    }
}
