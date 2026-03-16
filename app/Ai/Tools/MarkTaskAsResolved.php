<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\AgentTaskStatus;
use App\Enums\ToolCategory;
use App\Models\AgentTask;
use App\Models\ResourceLock;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Marks the active AgentTask as resolved, records a summary,
 * and releases the associated ResourceLock.
 *
 * This is the tool the TaskWorker agent calls when it has finished
 * taking corrective actions and considers the issue resolved.
 */
#[Category(ToolCategory::System)]
class MarkTaskAsResolved extends AbstractAgentTool
{
    protected bool $requiresApproval = false;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Mark Task Resolved';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Mark the current task as resolved with a summary of actions taken. Call this when you have finished fixing the issue.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{task_id: int, summary: string}  $arguments
     * @return array{success: bool, task_id: int, status: string}
     */
    public function execute(array $arguments): array
    {
        $taskId = $arguments['task_id'] ?? $this->activeTaskId;

        if (! $taskId) {
            return ['success' => false, 'task_id' => 0, 'status' => 'no_task_context'];
        }

        $task = AgentTask::query()->find($taskId);

        if (! $task) {
            return ['success' => false, 'task_id' => $taskId, 'status' => 'task_not_found'];
        }

        $task->update([
            'status' => AgentTaskStatus::Completed,
            'context_payload' => [
                ...$task->context_payload,
                'resolution_summary' => $arguments['summary'],
                'resolved_at' => now()->toIso8601String(),
            ],
        ]);

        ResourceLock::query()
            ->where('task_id', $taskId)
            ->delete();

        return [
            'success' => true,
            'task_id' => $taskId,
            'status' => 'resolved',
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->required(),
            'summary' => $schema->string()->required(),
        ];
    }
}
