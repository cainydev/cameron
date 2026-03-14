<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\ApprovalStatus;
use App\Models\PendingApproval;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves all pending approvals awaiting human review.
 */
class GetPendingApprovals extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve all pending approvals that are awaiting human review.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, array{id: int, tool_class: string, reasoning: string, expires_at: string|null}>
     */
    public function execute(array $arguments): array
    {
        return PendingApproval::query()
            ->where('status', ApprovalStatus::Waiting)
            ->with('task:id,goal_id,status')
            ->get(['id', 'task_id', 'tool_class', 'reasoning', 'expires_at', 'created_at'])
            ->toArray();
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
