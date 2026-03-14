<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Enums\AgentTaskStatus;
use App\Events\TaskStatusUpdated;
use App\Models\AgentTask;
use App\Models\ResourceLock;

class AbortTaskAction
{
    public function __construct(
        public readonly AgentTask $task,
    ) {}

    public function handle(): void
    {
        $this->task->update(['status' => AgentTaskStatus::Aborted]);
        ResourceLock::query()->where('task_id', $this->task->id)->delete();
        TaskStatusUpdated::dispatch($this->task->load('conversation'), AgentTaskStatus::Aborted);
    }
}
