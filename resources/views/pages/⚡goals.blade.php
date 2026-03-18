<?php

use App\Models\AgentGoal;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showCreateModal = false;

    // Form fields
    public string $name = '';
    public string $sensorToolClass = '';
    public string $sensorArguments = '{}';
    public string $conditions = '[]';
    public bool $isActive = true;
    public bool $isOneOff = false;
    public int $checkFrequencyMinutes = 60;
    public ?string $expiresAt = null;
    public string $initialContext = '';

    // Edit state
    public ?int $editingGoalId = null;

    #[Computed]
    public function goals()
    {
        return AgentGoal::query()
            ->whereHas('shop', fn ($q) => $q->where('user_id', Auth::id()))
            ->with(['memories' => fn ($q) => $q->latest()->limit(1)])
            ->withCount([
                'tasks',
                'tasks as running_tasks_count' => fn ($q) => $q->where('status', 'running'),
                'tasks as waiting_tasks_count' => fn ($q) => $q->where('status', 'waiting_approval'),
            ])
            ->latest()
            ->get();
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
            $shortName = $reflection->getShortName();
            $tools[$class] = $shortName;
        }

        asort($tools);

        return $tools;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingGoalId = null;
        $this->showCreateModal = true;
    }

    public function openEdit(int $goalId): void
    {
        $goal = $this->findGoal($goalId);

        $this->editingGoalId = $goalId;
        $this->name = $goal->name;
        $this->sensorToolClass = $goal->sensor_tool_class;
        $this->sensorArguments = json_encode($goal->sensor_arguments ?? [], JSON_PRETTY_PRINT);
        $this->conditions = json_encode($goal->conditions ?? [], JSON_PRETTY_PRINT);
        $this->isActive = $goal->is_active;
        $this->isOneOff = $goal->is_one_off;
        $this->checkFrequencyMinutes = $goal->check_frequency_minutes;
        $this->expiresAt = $goal->expires_at?->format('Y-m-d\TH:i');
        $this->initialContext = $goal->initial_context ?? '';
        $this->showCreateModal = true;
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

        $shop = Shop::query()->where('user_id', Auth::id())->firstOrFail();

        $data = [
            'name' => $this->name,
            'sensor_tool_class' => $this->sensorToolClass,
            'sensor_arguments' => json_decode($this->sensorArguments, true),
            'conditions' => json_decode($this->conditions, true),
            'is_active' => $this->isActive,
            'is_one_off' => $this->isOneOff,
            'check_frequency_minutes' => $this->checkFrequencyMinutes,
            'expires_at' => $this->expiresAt ?: null,
            'initial_context' => $this->initialContext,
        ];

        if ($this->editingGoalId) {
            $this->findGoal($this->editingGoalId)->update($data);
        } else {
            $shop->goals()->create($data);
        }

        $this->showCreateModal = false;
        $this->resetForm();
        unset($this->goals);
    }

    public function toggleActive(int $goalId): void
    {
        $goal = $this->findGoal($goalId);
        $goal->update(['is_active' => ! $goal->is_active]);
        unset($this->goals);
    }

    public function delete(int $goalId): void
    {
        $this->findGoal($goalId)->delete();
        unset($this->goals);
    }

    protected function findGoal(int $goalId): AgentGoal
    {
        return AgentGoal::query()
            ->whereHas('shop', fn ($q) => $q->where('user_id', Auth::id()))
            ->findOrFail($goalId);
    }

    protected function resetForm(): void
    {
        $this->name = '';
        $this->sensorToolClass = '';
        $this->sensorArguments = '{}';
        $this->conditions = '[]';
        $this->isActive = true;
        $this->isOneOff = false;
        $this->checkFrequencyMinutes = 60;
        $this->expiresAt = null;
        $this->initialContext = '';
    }
};
?>

<div wire:poll.10s>
<div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">Goals</flux:heading>
            <flux:text class="mt-1">Monitor metrics and trigger automated fixes</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            New Goal
        </flux:button>
    </div>

    <div class="space-y-3">
        @forelse($this->goals as $goal)
            <a href="{{ route('goal', $goal->id) }}" wire:navigate class="block group">
                <flux:card class="hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <flux:heading class="truncate">{{ $goal->name }}</flux:heading>
                                @if($goal->is_active)
                                    <flux:badge color="green" size="sm">Active</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Paused</flux:badge>
                                @endif
                                @if($goal->completed_at)
                                    <flux:badge color="blue" size="sm">Completed</flux:badge>
                                @endif
                                @if($goal->is_one_off)
                                    <flux:badge color="purple" size="sm">One-off</flux:badge>
                                @endif
                                @if($goal->running_tasks_count > 0)
                                    <flux:badge color="blue" size="sm" icon="loading">
                                        {{ $goal->running_tasks_count }} running
                                    </flux:badge>
                                @endif
                                @if($goal->waiting_tasks_count > 0)
                                    <flux:badge color="amber" size="sm" icon="clock">
                                        {{ $goal->waiting_tasks_count }} waiting
                                    </flux:badge>
                                @endif
                            </div>

                            <div class="flex items-center gap-4 mt-2 flex-wrap">
                                <flux:text class="text-xs text-zinc-500">
                                    <span class="font-medium">Sensor:</span> {{ class_basename($goal->sensor_tool_class) }}
                                </flux:text>
                                <flux:text class="text-xs text-zinc-500">
                                    <span class="font-medium">Every</span> {{ $goal->check_frequency_minutes }} min
                                </flux:text>
                                <flux:text class="text-xs text-zinc-500">
                                    {{ $goal->tasks_count }} task{{ $goal->tasks_count !== 1 ? 's' : '' }}
                                </flux:text>
                                @if($goal->last_checked_at)
                                    <flux:text class="text-xs text-zinc-500">
                                        Last checked {{ $goal->last_checked_at->diffForHumans() }}
                                    </flux:text>
                                @endif
                                @if($goal->expires_at)
                                    <flux:text class="text-xs text-amber-600 dark:text-amber-400">
                                        Expires {{ $goal->expires_at->format('M j, Y g:i A') }}
                                    </flux:text>
                                @endif
                            </div>

                            @if($goal->conditions)
                                <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                                    @foreach($goal->conditions as $condition)
                                        <span class="inline-flex items-center gap-1 text-xs bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 rounded px-1.5 py-0.5 font-mono">
                                            {{ $condition['metric'] ?? '' }} {{ $condition['operator'] ?? '' }} {{ $condition['value'] ?? '' }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if($goal->memories->isNotEmpty())
                                <div class="flex items-center gap-1.5 mt-2">
                                    <flux:icon name="light-bulb" class="size-3.5 text-pink-500 shrink-0" />
                                    <flux:text class="text-xs text-pink-600 dark:text-pink-400 truncate">{{ Str::limit($goal->memories->first()->insight, 80) }}</flux:text>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 shrink-0" x-on:click.prevent>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                :icon="$goal->is_active ? 'pause' : 'play'"
                                wire:click.stop="toggleActive({{ $goal->id }})"
                                :title="$goal->is_active ? 'Pause' : 'Activate'"
                            />
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="pencil"
                                wire:click.stop="openEdit({{ $goal->id }})"
                                title="Edit"
                            />
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                wire:click.stop="delete({{ $goal->id }})"
                                wire:confirm="Delete this goal and all its tasks?"
                                title="Delete"
                            />
                        </div>
                    </div>
                </flux:card>
            </a>
        @empty
            <flux:callout variant="info" icon="flag">
                <flux:callout.heading>No goals yet</flux:callout.heading>
                <flux:callout.text>
                    Create a goal to start monitoring metrics and triggering automated actions, or chat with Cameron to have one created for you.
                </flux:callout.text>
            </flux:callout>
        @endforelse
    </div>
</div>

<!-- Create / Edit Modal -->
<flux:modal wire:model="showCreateModal" class="max-w-2xl w-full">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingGoalId ? 'Edit Goal' : 'New Goal' }}</flux:heading>
            <flux:text class="mt-1">Configure what to monitor and when to act.</flux:text>
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
            <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">Cancel</flux:button>
            <flux:button variant="primary" wire:click="save">
                {{ $editingGoalId ? 'Save Changes' : 'Create Goal' }}
            </flux:button>
        </div>
    </div>
</flux:modal>
</div>
