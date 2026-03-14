<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Enums\AgentTaskStatus;
use App\Enums\ApprovalStatus;
use App\Jobs\RunTaskWorkerStep;
use App\Models\PendingApproval;

class ApproveTaskAction
{
    public function __construct(
        public readonly PendingApproval $approval,
        public readonly ?string $humanMessage = null,
    ) {}

    public function handle(): void
    {
        $this->approval->update(['status' => ApprovalStatus::Approved]);

        app($this->approval->tool_class)->execute($this->approval->payload);

        $task = $this->approval->task;

        if (! $task) {
            return;
        }

        $task->update(['status' => AgentTaskStatus::Running]);

        $injectMessage = 'The previously queued action has been APPROVED and executed successfully.';

        if ($this->humanMessage) {
            $injectMessage .= " Operator note: {$this->humanMessage}";
        }

        $injectMessage .= ' Continue or call MarkTaskAsResolved if complete.';

        RunTaskWorkerStep::dispatch($task, $injectMessage);
    }
}
