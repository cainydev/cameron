<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use App\Models\AgentGoal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves a summary of all active agent goals.
 */
#[Category(ToolCategory::Goals)]
class GetActiveGoalsSummary extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Active Goals';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve a summary of all active agent goals including their conditions and deadlines.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, array{id: int, name: string, conditions: array, is_one_off: bool, expires_at: string|null}>
     */
    public function execute(array $arguments): array
    {
        return AgentGoal::query()
            ->where('is_active', true)
            ->withCount('tasks')
            ->get(['id', 'name', 'conditions', 'is_one_off', 'expires_at', 'check_frequency_minutes', 'last_checked_at', 'created_at'])
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
