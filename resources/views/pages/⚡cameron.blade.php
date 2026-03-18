<?php

use App\Ai\Agents\CameronChat as CameronChatAgent;
use App\Ai\Tools\AbstractAgentTool;
use App\Jobs\GenerateConversationTitle;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Streaming\Events\ToolCall as ToolCallEvent;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $prompt = '';

    #[Locked]
    public ?string $conversationId = null;

    public bool $isProcessing = false;

    public string $streamingResponse = '';

    public bool $proMode = false;

    public function mount(?string $conversation = null): void
    {
        if (! $conversation) {
            return;
        }

        $exists = AgentConversation::query()
            ->where('id', $conversation)
            ->where('user_id', Auth::id())
            ->exists();

        if ($exists) {
            $this->conversationId = $conversation;
        }
    }

    /**
     * Build a per-message tool label+hidden map from its stored tool_calls and tool_results.
     *
     * @param  array<int, array{name: string, arguments: array<string, mixed>}>  $toolCalls
     * @param  iterable<\App\Ai\Tools\AbstractAgentTool>  $tools
     * @return array<string, array{label: string, hidden: bool, icon: string, color: string}>
     */
    public function resolveToolMeta(array $toolCalls, iterable $tools): array
    {
        /** @var array<string, \App\Ai\Tools\AbstractAgentTool> $byName */
        $byName = collect($tools)
            ->filter(fn ($t) => $t instanceof \App\Ai\Tools\AbstractAgentTool)
            ->keyBy(fn (\App\Ai\Tools\AbstractAgentTool $t) => (new \ReflectionClass($t))->getShortName())
            ->all();

        $meta = [];

        foreach ($toolCalls as $call) {
            $name = $call['name'] ?? '';
            $tool = $byName[$name] ?? null;
            $meta[$name] = [
                'label' => $tool ? $tool->label($call['arguments'] ?? []) : $name,
                'hidden' => $tool ? $tool->isHidden() : false,
                'icon' => $tool?->category()?->icon() ?? 'sparkles',
                'color' => $tool?->category()?->color() ?? 'indigo',
            ];
        }

        return $meta;
    }

    /**
     * Map a color key to Tailwind class strings for tool pills.
     */
    public function toolColorClasses(string $color): string
    {
        return match ($color) {
            'orange' => 'bg-orange-50 text-orange-600 border-orange-100 dark:bg-orange-950/40 dark:text-orange-400 dark:border-orange-900 hover:bg-orange-100 dark:hover:bg-orange-900/60',
            'blue' => 'bg-blue-50 text-blue-600 border-blue-100 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-900 hover:bg-blue-100 dark:hover:bg-blue-900/60',
            'green' => 'bg-green-50 text-green-600 border-green-100 dark:bg-green-950/40 dark:text-green-400 dark:border-green-900 hover:bg-green-100 dark:hover:bg-green-900/60',
            'cyan' => 'bg-cyan-50 text-cyan-600 border-cyan-100 dark:bg-cyan-950/40 dark:text-cyan-400 dark:border-cyan-900 hover:bg-cyan-100 dark:hover:bg-cyan-900/60',
            'purple' => 'bg-purple-50 text-purple-600 border-purple-100 dark:bg-purple-950/40 dark:text-purple-400 dark:border-purple-900 hover:bg-purple-100 dark:hover:bg-purple-900/60',
            'amber' => 'bg-amber-50 text-amber-600 border-amber-100 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900 hover:bg-amber-100 dark:hover:bg-amber-900/60',
            'pink' => 'bg-pink-50 text-pink-600 border-pink-100 dark:bg-pink-950/40 dark:text-pink-400 dark:border-pink-900 hover:bg-pink-100 dark:hover:bg-pink-900/60',
            'zinc' => 'bg-zinc-50 text-zinc-600 border-zinc-100 dark:bg-zinc-950/40 dark:text-zinc-400 dark:border-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-900/60',
            default => 'bg-indigo-50 text-indigo-600 border-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-400 dark:border-indigo-900 hover:bg-indigo-100 dark:hover:bg-indigo-900/60',
        };
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

    #[Computed]
    public function currentConversation(): ?AgentConversation
    {
        if (! $this->conversationId) {
            return null;
        }

        return AgentConversation::query()->find($this->conversationId);
    }

    public function sendMessage(string $promptText): void
    {
        $promptText = trim($promptText);

        validator(['prompt' => $promptText], ['prompt' => 'required|string|min:1'])->validate();

        $user = Auth::user();
        $isNewConversation = $this->conversationId === null;
        $this->isProcessing = true;
        $this->streamingResponse = '';
        $this->prompt = '';

        try {
            $shop = Shop::query()->where('user_id', Auth::id())->with('user')->firstOrFail();
            $agent = new CameronChatAgent($shop);

            /** @var array<string, AbstractAgentTool> $toolsByName */
            $toolsByName = collect($agent->tools())
                ->filter(fn ($t) => $t instanceof AbstractAgentTool)
                ->keyBy(fn (AbstractAgentTool $t) => (new \ReflectionClass($t))->getShortName())
                ->all();

            $model = $this->proMode ? 'gemini-3.1-pro-preview' : 'gemini-3.1-flash-lite-preview';

            if ($this->conversationId) {
                $stream = $agent->continue($this->conversationId, as: $user)
                    ->stream($promptText, model: $model);
            } else {
                $stream = $agent->forUser($user)
                    ->stream($promptText, model: $model);
            }

            $stream->then(function ($response) use ($promptText, $isNewConversation) {
                if (! $this->conversationId) {
                    $this->conversationId = $response->conversationId;
                }

                // Dispatch title generation for new conversations
                if ($isNewConversation && $this->conversationId) {
                    GenerateConversationTitle::dispatch($this->conversationId, $promptText);
                }

                // Redirect to the conversation URL so the sidebar syncs
                if ($isNewConversation && $this->conversationId) {
                    $this->dispatch('conversation-created');
                    $this->redirect(route('cameron', $this->conversationId), navigate: true);
                }
            });

            /** @var array<int, array{name: string, label: string}> $liveToolCalls */
            $liveToolCalls = [];

            foreach ($stream as $event) {
                if ($event instanceof ToolCallEvent) {
                    $toolName = $event->toolCall->name;
                    $tool = $toolsByName[$toolName] ?? null;

                    if (! $tool?->isHidden()) {
                        $liveToolCalls[] = [
                            'name' => $toolName,
                            'label' => $tool ? $tool->label($event->toolCall->arguments) : $toolName,
                        ];
                        $this->stream(
                            content: json_encode($liveToolCalls, JSON_UNESCAPED_UNICODE),
                            name: 'live-tool-calls',
                        );
                    }
                }

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
            unset($this->chatMessages, $this->currentConversation);
        }
    }

    public function startNewConversation(): void
    {
        $this->redirect(route('cameron'), navigate: true);
    }

    #[On('conversation-deleted')]
    public function onConversationDeleted(string $id): void
    {
        if ($this->conversationId === $id) {
            $this->redirect(route('cameron'), navigate: true);
        }
    }
};
?>

<div
    x-data="{
        thinking: false,
        streaming: false,
        optimisticMessage: '',
        liveToolCalls: [],
        toolModal: { open: false, name: '', arguments: '', result: '' },

        scrollToBottom(behavior = 'smooth') {
            window.scrollTo({ top: document.body.scrollHeight, behavior });
        },

        followStream() {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'instant' });
        },

        submit() {
            const text = $wire.prompt.trim();
            if (!text) return;
            this.optimisticMessage = text;
            this.thinking = true;
            this.streaming = false;
            $wire.prompt = '';
            const ta = this.$el.querySelector('textarea');
            if (ta) ta.value = '';
            $wire.sendMessage(text);
            this.$nextTick(() => this.scrollToBottom());
        },

        done() {
            this.optimisticMessage = '';
            this.thinking = false;
            this.streaming = false;
            this.liveToolCalls = [];
            this.$nextTick(() => {
                requestAnimationFrame(() => this.scrollToBottom());
            });
        },

        openToolModal(name, args, result) {
            this.toolModal = { open: true, name, arguments: args, result };
        },
    }"
    x-on:message-done.window="done()"
    x-init="
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

        const toolTarget = $el.querySelector('[wire\\:stream=live-tool-calls]');
        if (toolTarget) {
            new MutationObserver(() => {
                const raw = toolTarget.textContent.trim();
                if (raw) {
                    try { liveToolCalls = JSON.parse(raw); } catch {}
                    scrollToBottom();
                }
            }).observe(toolTarget, { childList: true, subtree: true, characterData: true });
        }

        requestAnimationFrame(() => {
            scrollToBottom('instant');
        });
    "
>
    <!-- Header -->
    <div class="fixed top-0 right-0 left-0 lg:left-72 z-10 flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-6 py-3">
        <div class="flex items-center gap-3">
            <flux:avatar initials="C" class="bg-indigo-500" />
            <div>
                <flux:heading>
                    {{ $this->currentConversation?->title ?? 'Cameron' }}
                </flux:heading>
                <flux:text class="text-xs">Front-Desk Manager</flux:text>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 p-0.5">
                <button
                    type="button"
                    wire:click="$set('proMode', false)"
                    class="px-3 py-1 rounded-md text-xs font-medium transition-colors {{ ! $proMode ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    Lite
                </button>
                <button
                    type="button"
                    wire:click="$set('proMode', true)"
                    class="px-3 py-1 rounded-md text-xs font-medium transition-colors {{ $proMode ? 'bg-indigo-500 text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    Pro
                </button>
            </div>
            <flux:button
                variant="ghost"
                size="sm"
                icon="plus"
                wire:click="startNewConversation"
            >
                New Chat
            </flux:button>
        </div>
    </div>

    <!-- Messages -->
    <div class="pt-20 pb-40" x-ref="scroller">
        <div class="mx-auto w-full max-w-2xl px-4 space-y-8" x-ref="messages">
                @forelse($this->chatMessages as $message)
                    @if($message->role === 'user')
                        <div class="flex justify-end" data-role="user">
                            <div class="max-w-[75%] bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 rounded-2xl rounded-tr-sm px-4 py-3">
                                <x-markdown class="prose prose-base prose-zinc dark:prose-invert max-w-none [&>*:first-child]:mt-0 [&>*:last-child]:mb-0">{!! $message->content !!}</x-markdown>
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
                                    @php
                                        $shop = \App\Models\Shop::query()->where('user_id', \Illuminate\Support\Facades\Auth::id())->with('user')->first();
                                        $agentTools = $shop ? (new \App\Ai\Agents\CameronChat($shop))->tools() : [];
                                        $toolMeta = $this->resolveToolMeta($message->tool_calls, $agentTools);
                                        $visibleCalls = app()->isLocal()
                                            ? $message->tool_calls
                                            : array_filter($message->tool_calls, fn ($c) => ! ($toolMeta[$c['name'] ?? '']['hidden'] ?? false));
                                    @endphp
                                    @if(count($visibleCalls) > 0)
                                        <div class="flex flex-wrap gap-1.5 mb-2">
                                            @foreach($visibleCalls as $call)
                                                @php
                                                    $callName = $call['name'] ?? '';
                                                    $callLabel = $toolMeta[$callName]['label'] ?? $callName;
                                                    $callArgs = json_encode($call['arguments'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                                    $resultData = collect($message->tool_results ?? [])
                                                        ->firstWhere('name', $callName);
                                                    $rawResult = $resultData['result'] ?? null;
                                                    if (is_string($rawResult)) {
                                                        $decoded = json_decode($rawResult, true);
                                                        $rawResult = $decoded ?? $rawResult;
                                                    }
                                                    $resultJson = $resultData
                                                        ? json_encode($rawResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                                        : '';
                                                @endphp
                                                @php
                                                    $pillColor = $toolMeta[$callName]['color'] ?? 'indigo';
                                                    $pillIcon = $toolMeta[$callName]['icon'] ?? 'sparkles';
                                                    $pillClasses = $this->toolColorClasses($pillColor);
                                                @endphp
                                                @php $isHidden = $toolMeta[$callName]['hidden'] ?? false; @endphp
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full border transition-colors cursor-pointer {{ $pillClasses }} {{ $isHidden ? 'opacity-40' : '' }}"
                                                    x-on:click="openToolModal(
                                                        {{ Js::from($callLabel) }},
                                                        {{ Js::from($callArgs) }},
                                                        {{ Js::from($resultJson) }}
                                                    )"
                                                >
                                                    <flux:icon name="{{ $pillIcon }}" variant="micro" class="size-3 shrink-0" />
                                                    {{ $callLabel }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                                <x-markdown class="prose prose-base prose-zinc dark:prose-invert max-w-none">{!! $message->content !!}</x-markdown>
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

                {{-- Live tool calls + thinking dots --}}
                <template x-if="thinking && !streaming">
                    <div class="flex items-start gap-3">
                        <img src="{{ Vite::asset('resources/images/cameron_sm.png') }}" alt="Cameron" class="size-7 rounded-full shrink-0 mt-0.5">
                        <div class="flex flex-col gap-2">
                            <template x-if="liveToolCalls.length > 0">
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="tool in liveToolCalls" :key="tool.name + tool.label">
                                        <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full border border-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-400 dark:border-indigo-900">
                                            <flux:icon name="sparkles" variant="micro" class="size-3 shrink-0" />
                                            <span x-text="tool.label"></span>
                                        </span>
                                    </template>
                                </div>
                            </template>
                            <div class="flex items-center gap-1 h-8">
                                <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-bounce [animation-delay:-0.3s]"></span>
                                <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-bounce [animation-delay:-0.15s]"></span>
                                <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-bounce"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Hidden wire:stream targets --}}
                <div class="hidden" wire:stream="live-tool-calls"></div>

                {{-- Streaming response --}}
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
            </div>
        </div>

    @error('prompt')
        <div class="fixed bottom-20 left-0 right-0 lg:left-64 z-20 flex justify-center">
            <div class="flex items-center gap-2 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 rounded-lg px-4 py-2 max-w-2xl w-full mx-4">
                <flux:icon name="exclamation-circle" variant="micro" class="size-4 shrink-0" />
                {{ $message }}
            </div>
        </div>
    @enderror

    <!-- Composer -->
    <flux:composer
        wire:model.live="prompt"
        label="Message"
        label:sr-only
        placeholder="Ask Cameron anything..."
        :disabled="$isProcessing"
        :rows="1"
        :max-rows="6"
        class="fixed! bottom-5 left-4 right-4 lg:left-72 z-20 max-w-2xl mx-auto bg-white dark:bg-zinc-800"
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

    <!-- Tool call detail modal -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        x-show="toolModal.open"
        x-cloak
        x-on:keydown.escape.window="toolModal.open = false"
        x-on:click.self="toolModal.open = false"
    >
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
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
                    <pre class="text-xs text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3 overflow-x-auto whitespace-pre-wrap break-words" x-text="toolModal.result"></pre>
                </div>
            </div>
        </div>
    </div>
</div>
