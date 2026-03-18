<?php

use App\Actions\Agent\AbortTaskAction;
use App\Actions\Agent\ApproveTaskAction;
use App\Actions\Agent\RejectTaskAction;
use App\Enums\AgentTaskStatus;
use App\Enums\ApprovalStatus;
use App\Enums\PlanStepStatus;
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
        return AgentTask::with(['goal', 'pendingApprovals', 'plan.steps.dependsOn', 'resourceLocks'])
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

    /**
     * @return array{color: string, icon: string, label: string}
     */
    public function stepStatusConfig(PlanStepStatus $status): array
    {
        return match ($status) {
            PlanStepStatus::Pending => ['color' => 'zinc', 'icon' => 'circle-stack'],
            PlanStepStatus::Running => ['color' => 'blue', 'icon' => 'loading'],
            PlanStepStatus::WaitingApproval => ['color' => 'amber', 'icon' => 'clock'],
            PlanStepStatus::Completed => ['color' => 'green', 'icon' => 'check'],
            PlanStepStatus::Failed => ['color' => 'red', 'icon' => 'x-mark'],
            PlanStepStatus::Skipped => ['color' => 'zinc', 'icon' => 'minus'],
        };
    }
};
?>

@php
    $task = $this->task;
    $isTerminal = in_array($task->status, [AgentTaskStatus::Completed, AgentTaskStatus::Aborted, AgentTaskStatus::Failed]);
@endphp

<div
    class="max-w-4xl mx-auto p-6"
    @if(! $isTerminal) wire:poll.3s @endif
>
    <!-- Header -->
    <div class="flex items-start justify-between gap-4 mb-6">
        <div class="flex items-start gap-3">
            <a href="{{ route('goal', $task->goal_id) }}" wire:navigate class="mt-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">
                <flux:icon name="arrow-left" class="size-5" />
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <flux:heading size="xl">{{ $task->goal->name }}</flux:heading>
                    <flux:badge color="zinc" size="sm">Task #{{ $task->id }}</flux:badge>
                </div>
                <div class="flex items-center gap-2 mt-1">
                    <flux:badge
                        :color="match($task->status) {
                            AgentTaskStatus::Running => 'blue',
                            AgentTaskStatus::WaitingApproval => 'amber',
                            AgentTaskStatus::Completed => 'green',
                            AgentTaskStatus::Failed => 'red',
                            default => 'zinc'
                        }"
                        size="sm"
                    >
                        @if($task->status === AgentTaskStatus::Running)
                            <flux:icon name="loading" class="animate-spin mr-1" variant="micro" />
                        @endif
                        {{ ucfirst(str_replace('_', ' ', $task->status->value)) }}
                    </flux:badge>
                    <flux:text class="text-xs text-zinc-500">
                        Created {{ $task->created_at->format('M j, g:i A') }}
                        · Updated {{ $task->updated_at->diffForHumans() }}
                    </flux:text>
                </div>
            </div>
        </div>
        @if(! $isTerminal)
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

    <div class="space-y-6">
        <!-- Section: Task Context -->
        @if($task->context_payload)
            <flux:card>
                <flux:heading class="text-sm mb-3">Task Context</flux:heading>

                @php $context = $task->context_payload; @endphp

                {{-- Sensor Data --}}
                @if(! empty($context['sensor_data']))
                    <div class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Sensor Data</p>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            @foreach($context['sensor_data'] as $key => $value)
                                <dt class="text-zinc-500 font-mono text-xs">{{ $key }}</dt>
                                <dd class="text-zinc-700 dark:text-zinc-300 font-mono text-xs">{{ is_array($value) ? json_encode($value) : $value }}</dd>
                            @endforeach
                        </dl>
                    </div>
                @endif

                {{-- Failed Conditions --}}
                @if(! empty($context['failed_conditions']))
                    <div class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Failed Conditions</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($context['failed_conditions'] as $condition)
                                <span class="inline-flex items-center gap-1 text-xs bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded px-2 py-1 font-mono border border-red-200 dark:border-red-800">
                                    {{ $condition['metric'] ?? '' }}
                                    <span class="font-bold">{{ $condition['operator'] ?? '' }}</span>
                                    {{ $condition['value'] ?? '' }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Temporal Urgency --}}
                @if(! empty($context['temporal_urgency']))
                    <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
                        <flux:callout.text>{{ $context['temporal_urgency'] }}</flux:callout.text>
                    </flux:callout>
                @endif

                {{-- Resolution Summary --}}
                @if(! empty($context['resolution_summary']))
                    <flux:callout variant="success" icon="check-circle" class="mb-4">
                        <flux:callout.heading>Resolution</flux:callout.heading>
                        <flux:callout.text>{{ $context['resolution_summary'] }}</flux:callout.text>
                    </flux:callout>
                @endif

                {{-- Raw JSON collapsible --}}
                <details class="mt-3">
                    <summary class="text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 cursor-pointer">Show raw JSON</summary>
                    <pre class="text-xs text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3 mt-2 overflow-x-auto whitespace-pre-wrap break-words max-h-64">{{ json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            </flux:card>
        @endif

        <!-- Section: Execution Plan -->
        @if($task->plan)
            @php
                $plan = $task->plan;
                $steps = $plan->steps;
                $completedSteps = $steps->where('status', PlanStepStatus::Completed)->count();
            @endphp
            <flux:card>
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading class="text-sm">Execution Plan</flux:heading>
                            <flux:badge
                                :color="match($plan->status->value) {
                                    'planning' => 'zinc',
                                    'executing' => 'blue',
                                    'waiting_approval' => 'amber',
                                    'completed' => 'green',
                                    'failed' => 'red',
                                    'aborted' => 'zinc',
                                    default => 'zinc'
                                }"
                                size="sm"
                            >
                                {{ ucfirst(str_replace('_', ' ', $plan->status->value)) }}
                            </flux:badge>
                        </div>
                        @if($plan->objective)
                            <flux:text class="text-sm mt-1">{{ $plan->objective }}</flux:text>
                        @endif
                    </div>
                </div>

                {{-- Metadata row --}}
                <div class="flex items-center gap-4 text-xs text-zinc-500 mb-4 flex-wrap">
                    <span>{{ $completedSteps }}/{{ $steps->count() }} steps completed</span>
                    @if($plan->retry_count > 0)
                        <flux:badge color="amber" size="sm">{{ $plan->retry_count }} {{ Str::plural('retry', $plan->retry_count) }}</flux:badge>
                    @endif
                    @if($plan->conversation_id)
                        <span class="font-mono">Conv #{{ $plan->conversation_id }}</span>
                    @endif
                </div>

                {{-- Plan Steps Stepper --}}
                @if($steps->isNotEmpty())
                    <flux:timeline>
                        @foreach($steps as $step)
                            @php
                                $stepConfig = $this->stepStatusConfig($step->status);
                            @endphp
                            <flux:timeline.item>
                                <flux:timeline.indicator :color="$stepConfig['color']">
                                    <flux:icon :name="$stepConfig['icon']" variant="micro" @class(['animate-spin' => $step->status === PlanStepStatus::Running]) />
                                </flux:timeline.indicator>
                                <flux:timeline.content>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-medium text-zinc-400">Step {{ $step->order }}</span>
                                        <flux:badge :color="$step->specialist_role->color()" size="sm" :icon="$step->specialist_role->icon()">
                                            {{ $step->specialist_role->label() }}
                                        </flux:badge>
                                        <flux:badge :color="$stepConfig['color']" size="sm">
                                            {{ ucfirst(str_replace('_', ' ', $step->status->value)) }}
                                        </flux:badge>
                                        @if($step->retry_count > 0)
                                            <flux:badge color="amber" size="sm">{{ $step->retry_count }} {{ Str::plural('retry', $step->retry_count) }}</flux:badge>
                                        @endif
                                    </div>

                                    <flux:text class="text-sm mt-1">{{ $step->action }}</flux:text>

                                    <div class="flex items-center gap-3 mt-1 text-xs text-zinc-400 flex-wrap">
                                        @if($step->depends_on_step_id && $step->dependsOn)
                                            <span>Depends on Step {{ $step->dependsOn->order }}</span>
                                        @endif
                                        @if($step->on_failure)
                                            <span>On failure: {{ $step->on_failure }}</span>
                                        @endif
                                        @if($step->started_at)
                                            <span>Started {{ $step->started_at->diffForHumans() }}</span>
                                        @endif
                                        @if($step->started_at && $step->completed_at)
                                            <span>Duration: {{ $step->started_at->diffForHumans($step->completed_at, true) }}</span>
                                        @endif
                                    </div>

                                    @if($step->output_summary)
                                        <div class="mt-2 p-2 bg-zinc-50 dark:bg-zinc-800 rounded text-xs text-zinc-600 dark:text-zinc-400 whitespace-pre-wrap">{{ $step->output_summary }}</div>
                                    @endif
                                </flux:timeline.content>
                            </flux:timeline.item>
                        @endforeach
                    </flux:timeline>
                @endif
            </flux:card>

            {{-- Working Memory --}}
            @if(! empty($plan->working_memory))
                <flux:card>
                    <details open>
                        <summary class="cursor-pointer">
                            <flux:heading class="text-sm inline">Working Memory</flux:heading>
                        </summary>
                        <div class="mt-3 space-y-3">
                            @foreach($plan->working_memory as $key => $value)
                                <div>
                                    <p class="text-xs font-semibold text-zinc-500 mb-1 font-mono">{{ $key }}</p>
                                    @php $text = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : (string) $value; @endphp
                                    @if(strlen($text) > 300)
                                        <details>
                                            <summary class="text-xs text-zinc-400 cursor-pointer">{{ Str::limit($text, 200) }}</summary>
                                            <pre class="text-xs text-zinc-600 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800 rounded p-2 mt-1 whitespace-pre-wrap break-words">{{ $text }}</pre>
                                        </details>
                                    @else
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $text }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                </flux:card>
            @endif
        @endif

        <!-- Section: Legacy Timeline (only for old-pipeline tasks without a plan) -->
        @if(! $task->plan_id)
            <div>
                <flux:heading class="text-sm mb-3">Activity</flux:heading>
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

                    @if($task->status === AgentTaskStatus::Running)
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

                    @if($task->status === AgentTaskStatus::Completed)
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

                    @if($task->status === AgentTaskStatus::Failed)
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
            </div>
        @endif

        <!-- Section: Pending Approvals -->
        @if($task->pendingApprovals->where('status', ApprovalStatus::Waiting)->isNotEmpty())
            <div>
                <flux:heading class="text-sm mb-3">Pending Approvals</flux:heading>
                <div class="space-y-3">
                    @foreach($task->pendingApprovals->where('status', ApprovalStatus::Waiting) as $approval)
                        <flux:callout variant="warning" icon="clock">
                            <flux:callout.heading>
                                {{ class_basename($approval->tool_class) }}
                            </flux:callout.heading>
                            <flux:callout.text>
                                {{ $approval->reasoning }}
                            </flux:callout.text>

                            @if($approval->payload)
                                <div class="mt-2">
                                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                        @foreach($approval->payload as $key => $value)
                                            <dt class="text-zinc-500 font-mono">{{ $key }}</dt>
                                            <dd class="text-zinc-700 dark:text-zinc-300 font-mono">{{ is_array($value) ? json_encode($value) : $value }}</dd>
                                        @endforeach
                                    </dl>
                                </div>
                            @endif

                            @if($approval->expires_at)
                                <div class="mt-2">
                                    <flux:badge color="amber" size="sm" icon="clock">
                                        Expires {{ $approval->expires_at->diffForHumans() }}
                                    </flux:badge>
                                </div>
                            @endif

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

        <!-- Section: Processed Actions -->
        @if($task->pendingApprovals->where('status', '!=', ApprovalStatus::Waiting)->isNotEmpty())
            <div>
                <flux:heading class="text-sm mb-3">Processed Actions</flux:heading>
                <div class="space-y-2">
                    @foreach($task->pendingApprovals->where('status', '!=', ApprovalStatus::Waiting) as $approval)
                        <div class="flex items-start gap-2 text-sm p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                            @if($approval->status->value === 'approved')
                                <flux:icon name="check-circle" class="text-green-500 shrink-0 mt-0.5" variant="micro" />
                            @else
                                <flux:icon name="x-circle" class="text-red-500 shrink-0 mt-0.5" variant="micro" />
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ class_basename($approval->tool_class) }}</span>
                                    <flux:badge size="sm" :color="$approval->status->value === 'approved' ? 'green' : 'red'">
                                        {{ ucfirst($approval->status->value) }}
                                    </flux:badge>
                                    <flux:text class="text-xs text-zinc-400">{{ $approval->updated_at->diffForHumans() }}</flux:text>
                                </div>
                                @if($approval->reasoning)
                                    <flux:text class="text-xs text-zinc-500 mt-1">{{ $approval->reasoning }}</flux:text>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Section: Resource Locks -->
        @if($task->resourceLocks->isNotEmpty())
            <div>
                <flux:heading class="text-sm mb-3">Resource Locks</flux:heading>
                <div class="space-y-2">
                    @foreach($task->resourceLocks as $lock)
                        <div class="flex items-center gap-3 text-sm p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                            <flux:icon
                                :name="$lock->isExpired() ? 'lock-open' : 'lock-closed'"
                                :class="$lock->isExpired() ? 'text-zinc-400' : 'text-amber-500'"
                                variant="micro"
                            />
                            <span class="font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $lock->resource_id }}</span>
                            <flux:badge
                                :color="$lock->isExpired() ? 'zinc' : 'amber'"
                                size="sm"
                            >
                                {{ $lock->isExpired() ? 'Expired' : 'Expires ' . $lock->expires_at->diffForHumans() }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
