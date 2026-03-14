<?php

use App\Ai\Agents\CameronChat as CameronChatAgent;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Exceptions\RateLimitedException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public string $prompt = '';

    #[Locked]
    public ?string $conversationId = null;

    public bool $isProcessing = false;

    public string $streamingResponse = '';

    public function mount(): void
    {
        $conversation = AgentConversation::query()
            ->where('user_id', Auth::id())
            ->latest('updated_at')
            ->first();

        if ($conversation) {
            $this->conversationId = $conversation->id;
        }
    }

    #[Computed]
    public function chatMessages(): Collection|Illuminate\Support\Collection
    {
        if (! $this->conversationId) {
            return collect();
        }

        return AgentConversationMessage::query()
            ->where('conversation_id', $this->conversationId)
            ->orderBy('created_at')
            ->get();
    }

    public function sendMessage(string $promptText): void
    {
        $promptText = trim($promptText);

        validator(['prompt' => $promptText], ['prompt' => 'required|string|min:1|max:10000'])->validate();

        $user = Auth::user();
        $this->isProcessing = true;
        $this->streamingResponse = '';
        $this->prompt = '';

        try {
            $shop = Shop::query()->where('user_id', Auth::id())->with('user')->firstOrFail();
            $agent = new CameronChatAgent($shop);

            if ($this->conversationId) {
                $stream = $agent->continue($this->conversationId, as: $user)
                    ->stream($promptText);
            } else {
                $stream = $agent->forUser($user)
                    ->stream($promptText);
            }

            $stream->then(function ($response) {
                if (! $this->conversationId) {
                    $this->conversationId = $response->conversationId;
                }
            });

            foreach ($stream as $event) {
                if ($event->text ?? null) {
                    $this->streamingResponse .= $event->text;
                    $this->stream(content: $this->streamingResponse, to: 'streaming-response');
                }
            }

            $this->streamingResponse = '';

        } catch (RateLimitedException $e) {
            $this->streamingResponse = '';
            $this->addError('prompt', 'The AI provider is rate-limited right now. Please wait a moment and try again.');
        } finally {
            $this->isProcessing = false;
            $this->dispatch('message-done');
        }
    }

    public function startNewConversation(): void
    {
        if ($this->conversationId) {
            AgentConversationMessage::query()->where('conversation_id', $this->conversationId)->delete();
            AgentConversation::query()->where('id', $this->conversationId)->delete();
        }

        $this->conversationId = null;
        $this->streamingResponse = '';
        unset($this->chatMessages);
    }
};
?>

<div class="flex flex-col h-[calc(100vh-0px)]">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 px-6 py-3">
        <div class="flex items-center gap-3">
            <flux:avatar initials="C" class="bg-indigo-500" />
            <div>
                <flux:heading>Cameron</flux:heading>
                <flux:text class="text-xs">Front-Desk Manager</flux:text>
            </div>
        </div>
        <flux:button
            variant="ghost"
            size="sm"
            icon="plus"
            wire:click="startNewConversation"
            wire:confirm="Start a new conversation?"
        >
            New Chat
        </flux:button>
    </div>

    <!-- Messages + Composer wrapped in a single Alpine component -->
    <div
        class="flex flex-col flex-1 min-h-0"
        x-data="{
            thinking: false,
            optimisticMessage: '',
            submit() {
                const text = $wire.prompt.trim();
                if (!text) return;
                this.optimisticMessage = text;
                this.thinking = true;
                $wire.prompt = '';
                const ta = this.$el.querySelector('textarea');
                if (ta) ta.value = '';
                this.$nextTick(() => this.scrollToBottom());
                $wire.sendMessage(text);
            },
            done() {
                this.optimisticMessage = '';
                this.thinking = false;
                this.$nextTick(() => this.scrollToBottom());
            },
            scrollToBottom() {
                const el = this.$refs.scroller;
                if (el) el.scrollTop = el.scrollHeight;
            },
        }"
        x-on:message-done.window="done()"
    >
        <!-- Scrollable messages -->
        <div class="flex-1 overflow-y-auto py-8" x-ref="scroller">
            <div class="mx-auto w-full max-w-2xl px-4 space-y-6">
                @forelse($this->chatMessages as $message)
                    @if($message->role === 'user')
                        <div class="flex justify-end">
                            <div class="max-w-[75%] bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 rounded-2xl rounded-tr-sm px-4 py-3">
                                <x-markdown class="prose prose-base prose-zinc dark:prose-invert max-w-none [&>*:first-child]:mt-0 [&>*:last-child]:mb-0">{{ $message->content }}</x-markdown>
                                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500 mt-2 block text-right">
                                    {{ $message->created_at->format('g:i A') }}
                                </flux:text>
                            </div>
                        </div>
                    @else
                        <div class="flex items-start gap-3">
                            <img src="{{ Vite::asset('resources/images/cameron_sm.png') }}" alt="Cameron" class="size-7 rounded-full shrink-0 mt-0.5">
                            <div class="flex-1 min-w-0">
                                @if($message->tool_calls)
                                    <div class="flex flex-wrap gap-1.5 mb-2">
                                        @foreach($message->tool_calls as $call)
                                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full border border-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-400 dark:border-indigo-900">
                                                <flux:icon name="sparkles" variant="micro" class="size-3 shrink-0" />
                                                {{ $call['name'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                <x-markdown class="prose prose-base prose-zinc dark:prose-invert max-w-none">{{ $message->content }}</x-markdown>
                                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500 mt-1 block">
                                    {{ $message->created_at->format('g:i A') }}
                                </flux:text>
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="flex flex-col items-center justify-center py-24 text-center" x-show="!optimisticMessage">
                        <flux:icon name="sparkles" class="h-10 w-10 text-indigo-500 mb-4" />
                        <flux:heading size="lg">Hello! I'm Cameron</flux:heading>
                        <flux:text class="mt-2 max-w-sm text-zinc-500">
                            I can help you manage goals, check on tasks, and handle pending approvals.
                        </flux:text>
                    </div>
                @endforelse

                {{-- Optimistic user bubble --}}
                <template x-if="optimisticMessage">
                    <div class="flex justify-end">
                        <div class="max-w-[75%] bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 rounded-2xl rounded-tr-sm px-4 py-3">
                            <p class="text-base whitespace-pre-wrap" x-text="optimisticMessage"></p>
                        </div>
                    </div>
                </template>

                {{-- Thinking dots --}}
                <template x-if="thinking">
                    <div class="flex items-start gap-3">
                        <img src="{{ Vite::asset('resources/images/cameron_sm.png') }}" alt="Cameron" class="size-7 rounded-full shrink-0 mt-0.5">
                        <div class="flex items-center gap-1 h-8">
                            <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-bounce [animation-delay:-0.3s]"></span>
                            <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-bounce [animation-delay:-0.15s]"></span>
                            <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-bounce"></span>
                        </div>
                    </div>
                </template>

                {{-- Streaming response — replaces dots once tokens arrive --}}
                @if($isProcessing && $streamingResponse)
                    <div class="flex items-start gap-3">
                        <img src="{{ Vite::asset('resources/images/cameron_sm.png') }}" alt="Cameron" class="size-7 rounded-full shrink-0 mt-0.5">
                        <div class="flex-1 min-w-0">
                            <div class="prose prose-base prose-zinc dark:prose-invert max-w-none" wire:stream="streaming-response">
                                {!! app(\Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($streamingResponse) !!}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Rate limit error -->
        @error('prompt')
            <div class="flex justify-center mb-2">
                <div class="flex items-center gap-2 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 rounded-lg px-4 py-2 max-w-2xl w-full mx-4">
                    <flux:icon name="exclamation-circle" variant="micro" class="size-4 shrink-0" />
                    {{ $message }}
                </div>
            </div>
        @enderror

        <!-- Composer -->
        <div class="py-5 flex justify-center">
            <div class="w-full max-w-2xl px-4">
                <flux:composer
                    wire:model.live="prompt"
                    label="Message"
                    label:sr-only
                    placeholder="Ask Cameron anything..."
                    :disabled="$isProcessing"
                    :rows="1"
                    :max-rows="6"
                    class="rounded-2xl! shadow-lg ring-1 ring-zinc-200 dark:ring-zinc-700"
                    x-init="
                        $nextTick(() => {
                            const ta = $el.querySelector('textarea');
                            if (!ta) return;
                            ta.addEventListener('keydown', (e) => {
                                if (e.key === 'Enter' && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
                                    e.preventDefault();
                                    submit();
                                }
                            });
                        });
                    "
                >
                    <x-slot name="actionsTrailing">
                        <flux:button
                            size="sm"
                            variant="primary"
                            icon="paper-airplane"
                            :disabled="$isProcessing"
                            x-on:click="submit()"
                        />
                    </x-slot>
                </flux:composer>
            </div>
        </div>
    </div>
</div>
