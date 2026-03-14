<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\TaskWorker;
use App\Enums\AgentTaskStatus;
use App\Events\TaskStatusUpdated;
use App\Models\AgentTask;
use App\Models\ResourceLock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;

class RunTaskWorkerStep implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AgentTask $task,
        public ?string $injectMessage = null,
        public int $stepCount = 0,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("task_{$this->task->id}"))->dontRelease(),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = $this->task->fresh();

        if ($this->stepCount >= 30) {
            $task->update(['status' => AgentTaskStatus::Failed]);
            ResourceLock::query()->where('task_id', $task->id)->delete();
            TaskStatusUpdated::dispatch($task->load('conversation'), AgentTaskStatus::Failed, 'Task exceeded maximum step limit.');
            Log::warning("Task [{$task->id}] exceeded 30 steps — marked Failed.");

            return;
        }

        if (in_array($task->status, [AgentTaskStatus::Aborted, AgentTaskStatus::Completed, AgentTaskStatus::Failed], true)) {
            return;
        }

        if ($task->status === AgentTaskStatus::WaitingApproval) {
            return;
        }

        $task->update(['status' => AgentTaskStatus::Running]);
        TaskStatusUpdated::dispatch($task->load('conversation'), AgentTaskStatus::Running);

        $promptText = $this->injectMessage ?? '[SYSTEM] Next step.';

        $task->loadMissing('goal.shop.user');
        $goal = $task->goal;

        $validMemories = $goal?->memories()
            ->where('expires_at', '>', now())
            ->pluck('insight')
            ->toArray();

        $worker = new TaskWorker(
            goalContext: json_encode($task->context_payload, JSON_THROW_ON_ERROR),
            taskId: $task->id,
            shop: $goal?->shop,
            urgencyDeadline: $task->context_payload['temporal_urgency'] ?? null,
            initialContext: $goal?->initial_context,
            activeMemories: $validMemories ?? [],
        );

        [$provider, $model] = app()->environment('local')
            ? [Lab::Gemini, 'gemini-2.0-flash']
            : [Lab::DeepSeek, 'deepseek-chat'];

        if ($task->conversation_id) {
            $worker->continue($task->conversation_id, (object) ['id' => null]);
        } else {
            $worker->forUser((object) ['id' => null]);
        }

        $response = $worker->prompt($promptText, provider: $provider, model: $model);

        if (! $task->conversation_id && $response->conversationId) {
            $task->update(['conversation_id' => $response->conversationId]);
        }

        $task->refresh();

        if (in_array($task->status, [AgentTaskStatus::Completed, AgentTaskStatus::Aborted, AgentTaskStatus::Failed, AgentTaskStatus::WaitingApproval], true)) {
            TaskStatusUpdated::dispatch($task->load('conversation'), $task->status);

            return;
        }

        self::dispatch($task, injectMessage: null, stepCount: $this->stepCount + 1);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $e): void
    {
        $task = $this->task->fresh();

        if ($task && ! in_array($task->status, [AgentTaskStatus::Completed, AgentTaskStatus::Aborted], true)) {
            $task->update(['status' => AgentTaskStatus::Failed]);
            ResourceLock::query()->where('task_id', $task->id)->delete();
            TaskStatusUpdated::dispatch($task->load('conversation'), AgentTaskStatus::Failed, $e->getMessage());
        }

        Log::error("RunTaskWorkerStep failed for Task [{$this->task->id}]: {$e->getMessage()}");
    }
}
