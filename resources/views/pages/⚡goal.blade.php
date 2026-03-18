<?php

use App\Enums\AgentTaskStatus;
use App\Enums\PlanStepStatus;
use App\Jobs\EvaluateSingleGoal;
use App\Models\AgentGoal;
use App\Models\AgentTask;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $goal;

    public bool $showEditModal = false;
    public bool $testRunning = false;
    public ?string $testResult = null;

    // Edit form fields
    public string $name = '';
    public string $sensorToolClass = '';
    public string $sensorArguments = '{}';
    public string $conditions = '[]';
    public bool $isActive = true;
    public bool $isOneOff = false;
    public int $checkFrequencyMinutes = 60;
    public ?string $expiresAt = null;
    public string $initialContext = '';

    #[Computed]
    public function agentGoal(): AgentGoal
    {
        return AgentGoal::query()
            ->with('memories')
            ->whereHas('shop', fn ($q) => $q->where('user_id', Auth::id()))
            ->findOrFail($this->goal);
    }

    #[Computed]
    public function tasks()
    {
        return AgentTask::query()
            ->with('plan.steps')
            ->where('goal_id', $this->goal)
            ->latest()
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function hasRunningTasks(): bool
    {
        return $this->tasks->contains(fn ($t) => in_array($t->status, [AgentTaskStatus::Running, AgentTaskStatus::WaitingApproval]));
    }

    #[Computed]
    public function availableSensorTools(): array
    {
        $toolFiles = glob(app_path('Ai/Tools/*.php'));
        $tools = [];

        foreach ($toolFiles as $file) {
            $class = 'App\\Ai\\Tools\\' . basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }
            if (! $reflection->isSubclassOf(\App\Ai\Tools\AbstractAgentTool::class)) {
                continue;
            }
            $tools[$class] = $reflection->getShortName();
        }

        asort($tools);

        return $tools;
    }

    public function openEdit(): void
    {
        $goal = $this->agentGoal;

        $this->name = $goal->name;
        $this->sensorToolClass = $goal->sensor_tool_class;
        $this->sensorArguments = json_encode($goal->sensor_arguments ?? [], JSON_PRETTY_PRINT);
        $this->conditions = json_encode($goal->conditions ?? [], JSON_PRETTY_PRINT);
        $this->isActive = $goal->is_active;
        $this->isOneOff = $goal->is_one_off;
        $this->checkFrequencyMinutes = $goal->check_frequency_minutes;
        $this->expiresAt = $goal->expires_at?->format('Y-m-d\TH:i');
        $this->initialContext = $goal->initial_context ?? '';
        $this->showEditModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'sensorToolClass' => 'required|string',
            'sensorArguments' => 'required|json',
            'conditions' => 'required|json',
            'checkFrequencyMinutes' => 'required|integer|min:1',
        ]);

        $this->agentGoal->update([
            'name' => $this->name,
            'sensor_tool_class' => $this->sensorToolClass,
            'sensor_arguments' => json_decode($this->sensorArguments, true),
            'conditions' => json_decode($this->conditions, true),
            'is_active' => $this->isActive,
            'is_one_off' => $this->isOneOff,
            'check_frequency_minutes' => $this->checkFrequencyMinutes,
            'expires_at' => $this->expiresAt ?: null,
            'initial_context' => $this->initialContext,
        ]);

        $this->showEditModal = false;
        unset($this->agentGoal);
    }

    public function toggleActive(): void
    {
        $goal = $this->agentGoal;
        $goal->update(['is_active' => ! $goal->is_active]);
        unset($this->agentGoal);
    }

    public function delete(): void
    {
        $this->agentGoal->delete();
        $this->redirect(route('goals'), navigate: true);
    }

    public function testSensor(): void
    {
        $this->testRunning = true;
        $this->testResult = null;

        try {
            $goal = $this->agentGoal;
            $sensorClass = $goal->sensor_tool_class;

            if (! class_exists($sensorClass)) {
                $this->testResult = json_encode(['error' => "Sensor class [{$sensorClass}] not found."], JSON_PRETTY_PRINT);

                return;
            }

            $sensor = app($sensorClass);

            if (! $sensor instanceof \App\Ai\Tools\AbstractAgentTool) {
                $this->testResult = json_encode(['error' => 'Sensor is not a valid tool.'], JSON_PRETTY_PRINT);

                return;
            }

            $goal->loadMissing('shop.user');
            $sensor->forShop($goal->shop);

            $result = $sensor->execute($goal->sensor_arguments ?? []);

            // Evaluate conditions against the result
            // If the sensor returned a list of rows, use the first row
            $metrics = is_array($result) && array_is_list($result) && isset($result[0]) && is_array($result[0])
                ? $result[0]
                : (is_array($result) ? $result : []);

            $conditionResults = [];
            foreach ($goal->conditions as $condition) {
                $metric = $condition['metric'] ?? null;
                $operator = $condition['operator'] ?? null;
                $threshold = $condition['value'] ?? null;
                $actual = $metrics[$metric] ?? null;

                $passes = match ($operator) {
                    '>' => $actual !== null && $actual > $threshold,
                    '>=' => $actual !== null && $actual >= $threshold,
                    '<' => $actual !== null && $actual < $threshold,
                    '<=' => $actual !== null && $actual <= $threshold,
                    '==' => $actual !== null && $actual == $threshold,
                    '!=' => $actual !== null && $actual != $threshold,
                    default => false,
                };

                $conditionResults[] = [
                    'condition' => "{$metric} {$operator} {$threshold}",
                    'actual' => $actual,
                    'passes' => $passes,
                ];
            }

            $this->testResult = json_encode([
                'sensor_data' => $result,
                'condition_results' => $conditionResults,
                'all_pass' => collect($conditionResults)->every(fn ($c) => $c['passes']),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            $this->testResult = json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
        } finally {
            $this->testRunning = false;
        }
    }

    public function triggerEvaluation(): void
    {
        $goal = $this->agentGoal;
        $goal->loadMissing('shop.user');

        EvaluateSingleGoal::dispatch($goal);

        unset($this->tasks);

        $this->testResult = null;
    }
};
?>

@php $goal = $this->agentGoal; @endphp

<div @if($this->hasRunningTasks) wire:poll.5s @endif>
<div class="p-6 max-w-4xl mx-auto">
    <!-- Back + Header -->
    <div class="flex items-start justify-between gap-4 mb-6">
        <div class="flex items-start gap-3">
            <a href="{{ route('goals') }}" wire:navigate class="mt-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">
                <flux:icon name="arrow-left" class="size-5" />
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <flux:heading size="xl">{{ $goal->name }}</flux:heading>
                    @if($goal->is_active)
                        <flux:badge color="green">Active</flux:badge>
                    @else
                        <flux:badge color="zinc">Paused</flux:badge>
                    @endif
                    @if($goal->completed_at)
                        <flux:badge color="blue">Completed</flux:badge>
                    @endif
                    @if($goal->is_one_off)
                        <flux:badge color="purple">One-off</flux:badge>
                    @endif
                </div>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    Created {{ $goal->created_at->format('M j, Y') }}
                    @if($goal->last_checked_at)
                        · Last checked {{ $goal->last_checked_at->diffForHumans() }}
                    @endif
                </flux:text>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <flux:button
                variant="ghost"
                size="sm"
                :icon="$goal->is_active ? 'pause' : 'play'"
                wire:click="toggleActive"
            >
                {{ $goal->is_active ? 'Pause' : 'Activate' }}
            </flux:button>
            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEdit">
                Edit
            </flux:button>
            <flux:button
                variant="ghost"
                size="sm"
                icon="trash"
                wire:click="delete"
                wire:confirm="Delete this goal and all its tasks?"
            >
                Delete
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left column: goal details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Sensor & Conditions -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading>Sensor & Conditions</flux:heading>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="play"
                        wire:click="testSensor"
                        :loading="$testRunning"
                    >
                        Test Now
                    </flux:button>
                </div>

                <div class="space-y-3">
                    <div class="flex items-start gap-3 text-sm">
                        <flux:icon name="cpu-chip" class="size-4 text-zinc-400 mt-0.5 shrink-0" />
                        <div>
                            <p class="font-medium text-zinc-700 dark:text-zinc-300">Sensor</p>
                            <p class="text-zinc-500 font-mono text-xs mt-0.5">{{ class_basename($goal->sensor_tool_class) }}</p>
                            @if($goal->sensor_arguments)
                                <pre class="text-xs text-zinc-500 mt-1 font-mono bg-zinc-50 dark:bg-zinc-800 rounded p-2 overflow-x-auto">{{ json_encode($goal->sensor_arguments, JSON_PRETTY_PRINT) }}</pre>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-start gap-3 text-sm">
                        <flux:icon name="funnel" class="size-4 text-zinc-400 mt-0.5 shrink-0" />
                        <div class="w-full">
                            <p class="font-medium text-zinc-700 dark:text-zinc-300">Conditions</p>
                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach($goal->conditions as $condition)
                                    <span class="inline-flex items-center gap-1 text-xs bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 rounded px-2 py-1 font-mono">
                                        {{ $condition['metric'] ?? '' }}
                                        <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ $condition['operator'] ?? '' }}</span>
                                        {{ $condition['value'] ?? '' }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-sm">
                        <flux:icon name="clock" class="size-4 text-zinc-400 shrink-0" />
                        <div>
                            <span class="text-zinc-500">Check every </span>
                            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $goal->check_frequency_minutes }} minutes</span>
                        </div>
                    </div>

                    @if($goal->expires_at)
                        <div class="flex items-center gap-3 text-sm">
                            <flux:icon name="calendar" class="size-4 text-amber-500 shrink-0" />
                            <div>
                                <span class="text-zinc-500">Expires </span>
                                <span class="font-medium text-amber-600 dark:text-amber-400">{{ $goal->expires_at->format('M j, Y g:i A') }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                @if($testResult !== null)
                    <div class="mt-4 border-t border-zinc-100 dark:border-zinc-700 pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Test Result</p>
                        @php $decoded = json_decode($testResult, true); @endphp
                        @if(isset($decoded['condition_results']))
                            <div class="space-y-1 mb-3">
                                @foreach($decoded['condition_results'] as $cr)
                                    <div class="flex items-center gap-2 text-sm">
                                        @if($cr['passes'])
                                            <flux:icon name="check-circle" class="size-4 text-green-500 shrink-0" />
                                        @else
                                            <flux:icon name="x-circle" class="size-4 text-red-500 shrink-0" />
                                        @endif
                                        <span class="font-mono text-xs">{{ $cr['condition'] }}</span>
                                        <span class="text-zinc-500 text-xs">(actual: {{ $cr['actual'] ?? 'n/a' }})</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex items-center gap-3 mb-3">
                                @if($decoded['all_pass'] ?? false)
                                    <flux:badge color="green">All conditions pass — no task would be triggered</flux:badge>
                                @else
                                    <flux:badge color="red">Conditions failed</flux:badge>
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="play"
                                        wire:click="triggerEvaluation"
                                        wire:confirm="This will spawn a task and trigger the agent pipeline. Continue?"
                                    >
                                        Trigger Task
                                    </flux:button>
                                @endif
                            </div>
                        @endif
                        <pre class="text-xs text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3 overflow-x-auto whitespace-pre-wrap break-words max-h-64">{{ $testResult }}</pre>
                    </div>
                @endif
            </flux:card>

            @if($goal->initial_context)
                <flux:card>
                    <flux:heading class="mb-3">Initial Context</flux:heading>
                    <flux:text class="text-sm whitespace-pre-wrap">{{ $goal->initial_context }}</flux:text>
                </flux:card>
            @endif

            {{-- Goal Memories --}}
            <flux:card>
                <div class="flex items-center gap-2 mb-3">
                    <flux:icon name="light-bulb" class="size-4 text-amber-500" />
                    <flux:heading class="text-sm">Goal Memories</flux:heading>
                </div>

                @forelse($goal->memories as $memory)
                    <div class="flex items-start justify-between gap-3 py-2 {{ ! $loop->last ? 'border-b border-zinc-100 dark:border-zinc-700' : '' }}">
                        <flux:text class="text-sm flex-1">{{ $memory->insight }}</flux:text>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($memory->expires_at)
                                <flux:badge color="amber" size="sm">Expires {{ $memory->expires_at->diffForHumans() }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">Permanent</flux:badge>
                            @endif
                            <flux:text class="text-xs text-zinc-400">{{ $memory->created_at->diffForHumans() }}</flux:text>
                        </div>
                    </div>
                @empty
                    <flux:text class="text-sm text-zinc-400">No memories recorded yet</flux:text>
                @endforelse
            </flux:card>
        </div>

        <!-- Right column: task history -->
        <div class="space-y-3">
            <flux:heading class="text-sm">Task History</flux:heading>

            @forelse($this->tasks as $task)
                <a href="{{ route('agent', $task->id) }}" wire:navigate class="block">
                    <div class="flex items-center gap-3 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors bg-white dark:bg-zinc-800">
                        <div class="shrink-0">
                            @php
                                $taskColor = match($task->status->value) {
                                    'running' => 'text-blue-500',
                                    'waiting_approval' => 'text-amber-500',
                                    'completed' => 'text-green-500',
                                    'failed' => 'text-red-500',
                                    'aborted' => 'text-zinc-400',
                                    default => 'text-zinc-400',
                                };
                                $taskIcon = match($task->status->value) {
                                    'running' => 'loading',
                                    'waiting_approval' => 'clock',
                                    'completed' => 'check-circle',
                                    'failed' => 'x-circle',
                                    'aborted' => 'minus-circle',
                                    default => 'circle-stack',
                                };
                            @endphp
                            <flux:icon name="{{ $taskIcon }}" class="size-5 {{ $taskColor }}" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
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
                                    {{ ucfirst(str_replace('_', ' ', $task->status->value)) }}
                                </flux:badge>
                            </div>
                            <flux:text class="text-xs text-zinc-500 mt-0.5">
                                {{ $task->created_at->format('M j, g:i A') }}
                            </flux:text>
                            @if($task->plan && $task->plan->steps->isNotEmpty())
                                <div class="flex items-center gap-1 mt-1">
                                    @foreach($task->plan->steps as $step)
                                        @php
                                            $dotColor = match($step->status) {
                                                PlanStepStatus::Pending => 'bg-zinc-300 dark:bg-zinc-600',
                                                PlanStepStatus::Running => 'bg-blue-500',
                                                PlanStepStatus::WaitingApproval => 'bg-amber-500',
                                                PlanStepStatus::Completed => 'bg-green-500',
                                                PlanStepStatus::Failed => 'bg-red-500',
                                                PlanStepStatus::Skipped => 'bg-zinc-300 dark:bg-zinc-600',
                                            };
                                        @endphp
                                        <span class="size-2 rounded-full {{ $dotColor }}" title="Step {{ $step->order }}: {{ $step->status->value }}"></span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <flux:icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" />
                    </div>
                </a>
            @empty
                <div class="text-center py-8 text-zinc-400">
                    <flux:icon name="circle-stack" class="size-8 mx-auto mb-2 opacity-50" />
                    <flux:text class="text-sm">No tasks yet</flux:text>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Edit Modal -->
<flux:modal wire:model="showEditModal" class="max-w-2xl w-full">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Edit Goal</flux:heading>
            <flux:text class="mt-1">Update this goal's configuration.</flux:text>
        </div>

        <div class="space-y-4">
            <flux:input
                wire:model="name"
                label="Name"
                placeholder="e.g. Keep ROAS above 3x"
            />

            <flux:select
                wire:model="sensorToolClass"
                label="Sensor Tool"
                placeholder="Choose a sensor..."
            >
                @foreach($this->availableSensorTools as $class => $label)
                    <flux:select.option value="{{ $class }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="sensorArguments"
                label="Sensor Arguments (JSON)"
                placeholder="{}"
                rows="3"
                class="font-mono text-sm"
            />

            <flux:textarea
                wire:model="conditions"
                label="Conditions (JSON)"
                placeholder='[{"metric": "roas", "operator": ">=", "value": 3}]'
                rows="4"
                class="font-mono text-sm"
            />

            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    wire:model="checkFrequencyMinutes"
                    label="Check Frequency (minutes)"
                    type="number"
                    min="1"
                />

                <flux:input
                    wire:model="expiresAt"
                    label="Expires At (optional)"
                    type="datetime-local"
                />
            </div>

            <flux:textarea
                wire:model="initialContext"
                label="Initial Context"
                placeholder="Any background context for the task worker..."
                rows="3"
            />

            <div class="flex items-center gap-6">
                <flux:checkbox wire:model="isActive" label="Active" />
                <flux:checkbox wire:model="isOneOff" label="One-off (deactivate after first success)" />
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Cancel</flux:button>
            <flux:button variant="primary" wire:click="save">Save Changes</flux:button>
        </div>
    </div>
</flux:modal>
</div>
