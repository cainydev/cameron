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
                    $this->stream(content: $this->streamingResponse, name: 'streaming-response');
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

<div class="flex flex-col grow min-h-0 overflow-hidden">
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
            streaming: false,
            optimisticMessage: '',
            toolModal: { open: false, name: '', arguments: '', result: '' },
            _userScrolled: false,
            _programmaticScroll: false,

            lastVisibleMessage() {
                const all = this.$refs.messages.querySelectorAll('[data-role]');
                let last = null;
                for (const el of all) {
                    if (el.offsetParent !== null || el.style.display !== 'none') {
                        last = el;
                    }
                }
                return last;
            },

            scrollToMessage(el, behavior = 'smooth') {
                if (!el) return;
                const scroller = this.$refs.scroller;
                const offset = el.offsetTop - this.$refs.messages.offsetTop;
                this._programmaticScroll = true;
                scroller.scrollTo({ top: Math.max(0, offset), behavior });
                if (behavior === 'instant') {
                    this._programmaticScroll = false;
                } else {
                    setTimeout(() => { this._programmaticScroll = false; }, 500);
                }
            },

            scrollToLast(behavior = 'smooth') {
                this._userScrolled = false;
                this.scrollToMessage(this.lastVisibleMessage(), behavior);
            },

            handleScroll() {
                if (this._programmaticScroll) return;
                if (this.streaming || this.thinking) {
                    this._userScrolled = true;
                }
            },

            followStream() {
                if (this._userScrolled) return;
                const s = this.$refs.scroller;
                const streamEl = this.$refs.streamingBubble;
                if (!streamEl) return;
                const offset = streamEl.offsetTop - this.$refs.messages.offsetTop;
                const streamBottom = offset + streamEl.offsetHeight;
                const viewBottom = s.scrollTop + s.clientHeight;
                if (streamBottom > viewBottom) {
                    this._programmaticScroll = true;
                    s.scrollTop = streamBottom - s.clientHeight;
                    setTimeout(() => { this._programmaticScroll = false; }, 50);
                }
            },

            submit() {
                const text = $wire.prompt.trim();
                if (!text) return;
                this.optimisticMessage = text;
                this.thinking = true;
                this.streaming = false;
                this._userScrolled = false;
                $wire.prompt = '';
                const ta = this.$el.querySelector('textarea');
                if (ta) ta.value = '';
                $wire.sendMessage(text);
                this.$nextTick(() => this.scrollToLast());
            },

            done() {
                this.optimisticMessage = '';
                this.thinking = false;
                this.streaming = false;
                this._userScrolled = false;
                this.$nextTick(() => {
                    requestAnimationFrame(() => this.scrollToLast());
                });
            },

            openToolModal(name, args, result) {
                this.toolModal = { open: true, name, arguments: args, result };
            },
        }"
        x-on:message-done.window="done()"
        x-init="
            $refs.scroller.addEventListener('scroll', () => handleScroll(), { passive: true });

            const target = $el.querySelector('[wire\\:stream=streaming-response]');
            if (target) {
                new MutationObserver(() => {
                    if (target.textContent.trim()) {
                        streaming = true;
                        thinking = false;
                        followStream();
                    }
                }).observe(target, { childList: true, subtree: true, characterData: true });
            }

            /* Scroll to the last message on initial page load, after Livewire has rendered */
            requestAnimationFrame(() => {
                scrollToLast('instant');
            });
        "
    >
        <!-- Scrollable messages -->
        <div class="flex-1 overflow-y-auto py-8" x-ref="scroller">
            <div class="mx-auto w-full max-w-2xl px-4 space-y-8" x-ref="messages">
                @forelse($this->chatMessages as $message)
                    @if($message->role === 'user')
                        <div class="flex justify-end" data-role="user">
                            <div class="max-w-[75%] bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 rounded-2xl rounded-tr-sm px-4 py-3">
                                <x-markdown class="prose prose-base prose-zinc dark:prose-invert max-w-none [&>*:first-child]:mt-0 [&>*:last-child]:mb-0">{{ $message->content }}</x-markdown>
                                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500 mt-2 block text-right">
                                    {{ $message->created_at->format('g:i A') }}
                                </flux:text>
                            </div>
                        </div>
                    @else
                        <div class="flex items-start gap-3" data-role="assistant">
                            <img src="{{ Vite::asset('resources/images/cameron_sm.png') }}" alt="Cameron" class="size-7 rounded-full shrink-0 mt-0.5">
                            <div class="flex-1 min-w-0">
                                @if($message->tool_calls)
                                    <div class="flex flex-wrap gap-1.5 mb-2">
                                        @foreach($message->tool_calls as $call)
                                            @php
                                                $callName = $call['name'] ?? '';
                                                $callArgs = json_encode($call['arguments'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                                // Find matching result
                                                $resultData = collect($message->tool_results ?? [])
                                                    ->firstWhere('name', $callName);
                                                $resultJson = $resultData
                                                    ? json_encode($resultData['result'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                                    : '';
                                            @endphp
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full border border-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-400 dark:border-indigo-900 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors cursor-pointer"
                                                x-on:click="openToolModal(
                                                    {{ Js::from($callName) }},
                                                    {{ Js::from($callArgs) }},
                                                    {{ Js::from($resultJson) }}
                                                )"
                                            >
                                                <flux:icon name="sparkles" variant="micro" class="size-3 shrink-0" />
                                                {{ $callName }}
                                            </button>
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
                    <div class="flex justify-end" data-role="user">
                        <div class="max-w-[75%] bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 rounded-2xl rounded-tr-sm px-4 py-3">
                            <p class="text-base whitespace-pre-wrap" x-text="optimisticMessage"></p>
                        </div>
                    </div>
                </template>

                {{-- Thinking dots — hidden once streaming starts --}}
                <template x-if="thinking && !streaming">
                    <div class="flex items-start gap-3">
                        <img src="{{ Vite::asset('resources/images/cameron_sm.png') }}" alt="Cameron" class="size-7 rounded-full shrink-0 mt-0.5">
                        <div class="flex items-center gap-1 h-8">
                            <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-bounce [animation-delay:-0.3s]"></span>
                            <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-bounce [animation-delay:-0.15s]"></span>
                            <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-bounce"></span>
                        </div>
                    </div>
                </template>

                {{-- Streaming response — always in DOM so wire:stream can target it --}}
                <div
                    class="flex items-start gap-3"
                    x-show="streaming"
                    x-cloak
                    x-ref="streamingBubble"
                    data-role="assistant"
                >
                    <img src="{{ Vite::asset('resources/images/cameron_sm.png') }}" alt="Cameron" class="size-7 rounded-full shrink-0 mt-0.5">
                    <div class="flex-1 min-w-0">
                        <div class="prose prose-base prose-zinc dark:prose-invert max-w-none" wire:stream="streaming-response"></div>
                    </div>
                </div>

                {{-- Spacer: ensures the last message can always scroll to the top of the viewport --}}
                <div aria-hidden="true" class="min-h-[calc(100vh-16rem)] shrink-0 pointer-events-none"></div>
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

        <!-- Tool call detail modal -->
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            x-show="toolModal.open"
            x-cloak
            x-on:keydown.escape.window="toolModal.open = false"
            x-on:click.self="toolModal.open = false"
        >
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center gap-2">
                        <flux:icon name="sparkles" variant="micro" class="size-4 text-indigo-500 shrink-0" />
                        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100" x-text="toolModal.name"></span>
                    </div>
                    <button
                        type="button"
                        class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors"
                        x-on:click="toolModal.open = false"
                    >
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>
                <div class="overflow-y-auto flex-1 divide-y divide-zinc-100 dark:divide-zinc-800">
                    <div class="px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Parameters</p>
                        <pre class="text-xs text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3 overflow-x-auto whitespace-pre-wrap break-words" x-text="toolModal.arguments || '(none)'"></pre>
                    </div>
                    <div class="px-5 py-4" x-show="toolModal.result">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Response</p>
                        <pre class="text-xs text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3 overflow-x-auto whitespace-pre-wrap break-words max-h-64" x-text="toolModal.result"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
