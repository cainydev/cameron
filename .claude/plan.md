# Chat UI Implementation Plan

## Overview
Build a modern chat interface using Flux UI Pro and Laravel Echo/Reverb with:
- **Cameron Chat** pinned at the top (primary conversational AI)
- **Task Agents** listed below with real-time status updates
- **Approval indicators** for tasks needing user action

---

## Architecture

### Data Model
The existing models are already in place:
- `AgentConversation` - stores chat conversations (UUID primary key)
- `AgentConversationMessage` - stores messages with role, content, attachments
- `AgentTask` - task agents with status tracking
- `PendingApproval` - approvals waiting for user action
- `AgentGoal` - goals that spawn tasks

### Status Tracking
`AgentTaskStatus` enum:
- `pending` → waiting to run
- `running` → actively processing
- `waiting_approval` → needs user action (show indicator)
- `completed` → finished successfully
- `stale` → outdated
- `aborted` → cancelled
- `failed` → error state

---

## Components to Create

### 1. Livewire Components

#### `app/Livewire/Chat/Index.php`
Main chat page component that:
- Loads the Cameron conversation for the current user
- Loads active task agents with their status
- Listens to Echo channels for real-time updates
- Handles conversation switching

#### `app/Livewire/Chat/CameronChat.php`
The Cameron conversational component:
- Manages the active conversation with Cameron
- Uses `wire:stream` for streaming AI responses
- Handles message sending via `flux:composer`

#### `app/Livewire/Chat/TaskAgentList.php`
Task agents sidebar:
- Lists all task agents (tasks) for the user
- Shows status badges (running, pending, needs approval)
- Sorts by: needs_approval first, then running, then by updated_at
- Real-time updates via Echo

#### `app/Livewire/Chat/TaskAgentItem.php`
Individual task agent item:
- Displays task name, status, goal context
- Shows approval indicator when `waiting_approval`
- Clickable to view task details/approval actions

### 2. Views

#### `resources/views/livewire/chat/index.blade.php`
Main layout:
```blade
<div class="flex h-full">
    <!-- Sidebar: Task Agents -->
    <div class="w-80 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.group heading="Cameron">
            <flux:sidebar.item icon="sparkles" :current="$activeChat === 'cameron'">
                Cameron Chat
            </flux:sidebar.item>
        </flux:sidebar.group>

        <flux:sidebar.group heading="Task Agents">
            <livewire:chat.task-agent-list />
        </flux:sidebar.group>
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col">
        @if($activeChat === 'cameron')
            <livewire:chat.cameron-chat />
        @else
            <livewire:chat.task-conversation :taskId="$activeChat" />
        @endif
    </div>
</div>
```

#### `resources/views/livewire/chat/cameron-chat.blade.php`
Cameron chat interface:
```blade
<div class="flex flex-col h-full">
    <!-- Messages Area -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        @foreach($messages as $message)
            <flux:callout :variant="$message->role === 'user' ? 'secondary' : 'default'">
                <flux:callout.heading>
                    {{ $message->role === 'user' ? 'You' : 'Cameron' }}
                </flux:callout.heading>
                <flux:callout.text wire:stream="streaming-response">
                    {{ $message->content }}
                </flux:callout.text>
            </flux:callout>
        @endforeach
    </div>

    <!-- Composer -->
    <div class="border-t border-zinc-200 dark:border-zinc-700 p-4">
        <form wire:submit="sendMessage">
            <flux:composer
                wire:model="prompt"
                label="Message"
                label:sr-only
                placeholder="Ask Cameron anything..."
                :disabled="$isProcessing"
            >
                <x-slot name="actionsTrailing">
                    <flux:button
                        type="submit"
                        size="sm"
                        variant="primary"
                        icon="paper-airplane"
                        :disabled="$isProcessing"
                    />
                </x-slot>
            </flux:composer>
        </form>
    </div>
</div>
```

#### `resources/views/livewire/chat/task-agent-list.blade.php`
Task agent sidebar list:
```blade
<div class="space-y-1">
    @forelse($tasks as $task)
        <flux:sidebar.item
            :icon="match($task->status) {
                'running' => 'loader-circle',
                'waiting_approval' => 'clock',
                'completed' => 'check-circle',
                'failed' => 'x-circle',
                default => 'circle'
            }"
            :badge="$task->status === 'waiting_approval' ? '!' : null"
            badge:color="amber"
            wire:click="selectTask({{ $task->id }})"
            :current="$selectedTaskId === $task->id"
        >
            <div class="flex items-center gap-2">
                <span>{{ $task->goal->name }}</span>
                @if($task->status === 'running')
                    <flux:icon name="loader-circle" class="animate-spin" variant="micro" />
                @endif
            </div>
            <flux:text class="text-xs">{{ $task->status->label() }}</flux:text>
        </flux:sidebar.item>
    @empty
        <flux:text class="text-zinc-500 text-sm p-2">No active tasks</flux:text>
    @endforelse
</div>
```

### 3. Broadcasting Events

#### `app/Events/TaskStatusUpdated.php`
Broadcasted when a task's status changes:
```php
class TaskStatusUpdated implements ShouldBroadcast
{
    public function __construct(
        public int $taskId,
        public AgentTaskStatus $status,
        public ?string $message = null,
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->userId);
    }
}
```

#### `app/Events/NewMessage.php`
Broadcasted when a new message arrives:
```php
class NewMessage implements ShouldBroadcast
{
    public function __construct(
        public string $conversationId,
        public AgentConversationMessage $message,
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel('conversation.' . $this->conversationId);
    }
}
```

#### `app/Events/ApprovalRequired.php`
Broadcasted when a task needs approval:
```php
class ApprovalRequired implements ShouldBroadcast
{
    public function __construct(
        public int $taskId,
        public int $approvalId,
        public string $toolClass,
        public string $reasoning,
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->userId);
    }
}
```

### 4. Routes

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('chat', \App\Livewire\Chat\Index::class)->name('chat');
    Route::get('chat/{conversation}', \App\Livewire\Chat\Index::class)->name('chat.conversation');
});
```

### 5. Channel Authorization

```php
// routes/channels.php
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{id}', function ($user, $id) {
    return \App\Models\AgentConversation::where('id', $id)
        ->where('user_id', $user->id)
        ->exists();
});
```

---

## Implementation Order

### Phase 1: Core Chat UI
1. Create `Chat/Index.php` Livewire component
2. Create `chat/index.blade.php` view with sidebar layout
3. Create `Chat/CameronChat.php` component with streaming
4. Create `cameron-chat.blade.php` with message list and composer
5. Add route `/chat`

### Phase 2: Task Agent List
1. Create `Chat/TaskAgentList.php` component
2. Create `task-agent-list.blade.php` view
3. Add status badge logic and icons
4. Wire up task selection

### Phase 3: Real-time Updates
1. Create `TaskStatusUpdated` event
2. Create `NewMessage` event
3. Create `ApprovalRequired` event
4. Configure channels in `routes/channels.php`
5. Add Echo listeners in Livewire components

### Phase 4: Task Conversation View
1. Create `Chat/TaskConversation.php` component
2. Show task context and execution log
3. Add approval/reject actions for pending approvals

---

## UI Components Used

| Component | Usage |
|-----------|-------|
| `flux:sidebar` | Main navigation |
| `flux:sidebar.item` | Chat/Task list items |
| `flux:sidebar.group` | Grouped sections |
| `flux:composer` | Message input |
| `flux:callout` | Message bubbles |
| `flux:badge` | Status indicators |
| `flux:icon` | Status icons (loader-circle, check, etc.) |
| `flux:button` | Send, approve, reject |
| `flux:timeline` | Task execution log |
| `flux:card` | Task details |
| `flux:heading` | Titles |
| `flux:text` | Body text |

---

## Key UX Behaviors

1. **Cameron Chat Always Pinned** - Cameron is always at the top of the sidebar, separate from tasks
2. **Approval Badge** - Tasks needing approval show an amber `!` badge
3. **Running Indicator** - Active tasks show spinning loader icon
4. **Real-time Updates** - Status changes update instantly via Echo
5. **Streaming Responses** - Cameron's responses stream in using `wire:stream`
6. **Clean Minimal UI** - Use existing Flux patterns, no custom CSS
