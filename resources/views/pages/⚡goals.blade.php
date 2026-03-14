<?php

use App\Models\AgentGoal;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function goals()
    {
        return AgentGoal::query()
            ->withCount('tasks')
            ->latest()
            ->get();
    }
};
?>

<div class="p-6">
    <flux:heading size="xl">Goals</flux:heading>
    <flux:text class="mt-2 mb-6">Manage your automation goals</flux:text>

    <div class="space-y-4">
        @forelse($this->goals as $goal)
            <flux:card class="hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading>{{ $goal->name }}</flux:heading>
                        <flux:text class="mt-1 text-sm">
                            {{ $goal->tasks_count }} task{{ $goal->tasks_count !== 1 ? 's' : '' }}
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($goal->is_active)
                            <flux:badge color="green" size="sm">Active</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                        @endif
                        @if($goal->completed_at)
                            <flux:badge color="blue" size="sm">Completed</flux:badge>
                        @endif
                    </div>
                </div>
                @if($goal->expires_at)
                    <flux:text class="mt-3 text-xs text-zinc-500">
                        Expires: {{ $goal->expires_at->format('M j, Y g:i A') }}
                    </flux:text>
                @endif
            </flux:card>
        @empty
            <flux:callout variant="info">
                <flux:callout.heading>No goals yet</flux:callout.heading>
                <flux:callout.text>
                    Chat with Cameron to create your first automation goal.
                </flux:callout.text>
            </flux:callout>
        @endforelse
    </div>
</div>