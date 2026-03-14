<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Enums\AgentTaskStatus;
use App\Enums\ApprovalStatus;
use App\Jobs\RunTaskWorkerStep;
use App\Models\PendingApproval;

class RejectTaskAction
{
    public function __construct(
        public readonly PendingApproval $approval,
        public readonly ?string $humanMessage = null,
    ) {}

    public function handle(): void
    {
        $this->approval->update(['status' => ApprovalStatus::Rejected]);

        $task = $this->approval->task;
        $task->update(['status' => AgentTaskStatus::Running]);

        $injectMessage = 'The action was REJECTED.';

        if ($this->humanMessage) {
            $injectMessage .= " Reason: {$this->humanMessage}";
        }

        $injectMessage .= ' Do not retry. Find an alternative or call MarkTaskAsResolved.';

        RunTaskWorkerStep::dispatch($task, $injectMessage);
    }
}
