<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\AgentGoalMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Records a valuable insight for a goal so future workers can learn from it.
 */
class AddGoalMemory extends AbstractAgentTool
{
    protected bool $isReadOnly = false;

    protected bool $requiresApproval = false;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Record a valuable insight or finding for a goal so future workers investigating this goal can learn from it. Set valid_for_hours based on how long this fact will remain relevant.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{goal_id: int, insight: string, valid_for_hours: int}  $arguments
     * @return array{success: bool, expires_at: string}
     */
    public function execute(array $arguments): array
    {
        AgentGoalMemory::query()->create([
            'agent_goal_id' => $arguments['goal_id'],
            'insight' => $arguments['insight'],
            'expires_at' => now()->addHours($arguments['valid_for_hours']),
        ]);

        return [
            'success' => true,
            'expires_at' => now()->addHours($arguments['valid_for_hours'])->toIso8601String(),
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'goal_id' => $schema->integer()->required(),
            'insight' => $schema->string()->required(),
            'valid_for_hours' => $schema->integer()->required(),
        ];
    }
}
