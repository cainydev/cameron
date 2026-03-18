<?php

use App\Models\AgentConversation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public function deleteConversation(string $id): void
    {
        AgentConversation::query()
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        $this->dispatch('conversation-deleted', id: $id);
    }

    #[On('conversation-created')]
    public function refresh(): void {}
};
?>

<div>
    @php
        $conversations = \App\Models\AgentConversation::query()
            ->where('user_id', auth()->id())
            ->latest('updated_at')
            ->limit(20)
            ->get();
        $currentId = request()->route('conversation');
    @endphp

    <div class="flex items-center justify-between px-2 mb-1">
        <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Chats</span>
        <a href="{{ route('cameron') }}" wire:navigate class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors" title="New Chat">
            <flux:icon name="plus" variant="micro" class="size-4" />
        </a>
    </div>

    @forelse($conversations as $conversation)
        <div class="group relative flex items-center">
            <flux:sidebar.item
                icon="chat-bubble-left"
                :href="route('cameron', $conversation->id)"
                :current="$currentId === $conversation->id"
                wire:navigate
                class="flex-1 truncate pe-7!"
            >
                {{ $conversation->title ?? 'New conversation' }}
            </flux:sidebar.item>
            <button
                type="button"
                wire:click="deleteConversation('{{ $conversation->id }}')"
                wire:confirm="Delete this conversation?"
                class="absolute right-1 p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity text-zinc-400 hover:text-red-500 dark:hover:text-red-400"
                title="Delete"
            >
                <flux:icon name="trash" variant="micro" class="size-3.5" />
            </button>
        </div>
    @empty
        <div class="px-2 py-1">
            <flux:text class="text-sm text-zinc-400">No conversations yet</flux:text>
        </div>
    @endforelse
</div>