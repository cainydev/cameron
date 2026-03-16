<?php

use App\Actions\Agent\AbortTaskAction;
use App\Actions\Agent\ApproveTaskAction;
use App\Actions\Agent\RejectTaskAction;
use App\Enums\ApprovalStatus;
use App\Models\AgentTask;
use App\Models\PendingApproval;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $agent;

    public ?string $approvalNote = null;

    #[Computed]
    public function task(): AgentTask
    {
        return AgentTask::with(['goal', 'pendingApprovals'])
            ->findOrFail($this->agent);
    }

    public function approve(int $approvalId): void
    {
        $approval = PendingApproval::query()->findOrFail($approvalId);

        (new ApproveTaskAction($approval, $this->approvalNote))->handle();

        $this->approvalNote = null;
        unset($this->task);
    }

    public function reject(int $approvalId): void
    {
        $approval = PendingApproval::query()->findOrFail($approvalId);

        (new RejectTaskAction($approval, $this->approvalNote))->handle();

        $this->approvalNote = null;
        unset($this->task);
    }

    public function abort(): void
    {
        (new AbortTaskAction($this->task))->handle();

        unset($this->task);
    }
};
?>

@php
    $task = $this->task;
@endphp

<div class="flex flex-col h-[calc(100vh-0px)]">
    <!-- Header -->
    <div class="border-b border-zinc-200 dark:border-zinc-700 px-4 py-3">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading>{{ $task->goal->name }}</flux:heading>
                <div class="flex items-center gap-2 mt-1">
                    <flux:badge
                        :color="match($task->status->value) {
                            'running' => 'blue',
                            'waiting_approval' => 'amber',
                            'completed' => 'green',
                            'failed' => 'red',
                            default => 'zinc'
                        }"
                        size="sm"
                    >
                        @if($task->status->value === 'running')
                            <flux:icon name="loading" class="animate-spin mr-1" variant="micro" />
                        @endif
                        {{ ucfirst(str_replace('_', ' ', $task->status->value)) }}
                    </flux:badge>
                    <flux:text class="text-xs text-zinc-500">
                        Updated {{ $task->updated_at->diffForHumans() }}
                    </flux:text>
                </div>
            </div>
            @if(! in_array($task->status->value, ['completed', 'aborted', 'failed']))
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="x-circle"
                    wire:click="abort"
                    wire:confirm="Abort this task?"
                >
                    Abort
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Task Details -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <!-- Context -->
        @if($task->context_payload)
            <flux:card>
                <flux:heading class="text-sm">Task Context</flux:heading>
                <flux:text class="mt-2 text-sm whitespace-pre-wrap">
                    {{ json_encode($task->context_payload, JSON_PRETTY_PRINT) }}
                </flux:text>
            </flux:card>
        @endif

        <!-- Timeline of activity -->
        <flux:heading class="text-sm">Activity</flux:heading>

        <flux:timeline>
            <flux:timeline.item>
                <flux:timeline.indicator color="blue">
                    <flux:icon name="play" variant="micro" />
                </flux:timeline.indicator>
                <flux:timeline.content>
                    <flux:heading class="text-sm">Task Created</flux:heading>
                    <flux:text class="text-xs">{{ $task->created_at->format('M j, g:i A') }}</flux:text>
                </flux:timeline.content>
            </flux:timeline.item>

            @if($task->status->value === 'running')
                <flux:timeline.item>
                    <flux:timeline.indicator color="blue">
                        <flux:icon name="loading" class="animate-spin" variant="micro" />
                    </flux:timeline.indicator>
                    <flux:timeline.content>
                        <flux:heading class="text-sm">Processing...</flux:heading>
                        <flux:text class="text-xs">Agent is working on this task</flux:text>
                    </flux:timeline.content>
                </flux:timeline.item>
            @endif

            @if($task->status->value === 'completed')
                <flux:timeline.item>
                    <flux:timeline.indicator color="green">
                        <flux:icon name="check" variant="micro" />
                    </flux:timeline.indicator>
                    <flux:timeline.content>
                        <flux:heading class="text-sm">Completed</flux:heading>
                        <flux:text class="text-xs">{{ $task->updated_at->format('M j, g:i A') }}</flux:text>
                    </flux:timeline.content>
                </flux:timeline.item>
            @endif

            @if($task->status->value === 'failed')
                <flux:timeline.item>
                    <flux:timeline.indicator color="red">
                        <flux:icon name="x-mark" variant="micro" />
                    </flux:timeline.indicator>
                    <flux:timeline.content>
                        <flux:heading class="text-sm">Failed</flux:heading>
                        <flux:text class="text-xs">{{ $task->updated_at->format('M j, g:i A') }}</flux:text>
                    </flux:timeline.content>
                </flux:timeline.item>
            @endif
        </flux:timeline>

        <!-- Pending Approvals -->
        @if($task->pendingApprovals->where('status', ApprovalStatus::Waiting)->isNotEmpty())
            <div class="mt-6">
                <flux:heading class="text-sm">Pending Approvals</flux:heading>
                <div class="space-y-3 mt-3">
                    @foreach($task->pendingApprovals->where('status', ApprovalStatus::Waiting) as $approval)
                        <flux:callout variant="warning" icon="clock">
                            <flux:callout.heading>
                                {{ class_basename($approval->tool_class) }}
                            </flux:callout.heading>
                            <flux:callout.text>
                                {{ $approval->reasoning }}
                            </flux:callout.text>
                            <div class="mt-3 space-y-2">
                                <flux:input
                                    wire:model="approvalNote"
                                    placeholder="Optional note to the agent..."
                                    size="sm"
                                />
                                <div class="flex gap-2">
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="check"
                                        wire:click="approve({{ $approval->id }})"
                                        wire:confirm="Approve this action?"
                                    >
                                        Approve
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="x-mark"
                                        wire:click="reject({{ $approval->id }})"
                                        wire:confirm="Reject this action?"
                                    >
                                        Reject
                                    </flux:button>
                                </div>
                            </div>
                        </flux:callout>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Processed Approvals -->
        @if($task->pendingApprovals->where('status', '!=', ApprovalStatus::Waiting)->isNotEmpty())
            <div class="mt-6">
                <flux:heading class="text-sm">Processed Actions</flux:heading>
                <div class="space-y-2 mt-3">
                    @foreach($task->pendingApprovals->where('status', '!=', ApprovalStatus::Waiting) as $approval)
                        <div class="flex items-center gap-2 text-sm p-2 rounded bg-zinc-50 dark:bg-zinc-800">
                            @if($approval->status->value === 'approved')
                                <flux:icon name="check-circle" class="text-green-500" variant="micro" />
                            @else
                                <flux:icon name="x-circle" class="text-red-500" variant="micro" />
                            @endif
                            <span>{{ class_basename($approval->tool_class) }}</span>
                            <flux:badge size="sm" :color="$approval->status->value === 'approved' ? 'green' : 'red'">
                                {{ ucfirst($approval->status->value) }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>