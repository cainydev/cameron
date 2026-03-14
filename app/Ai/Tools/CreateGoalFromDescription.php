<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\GoalArchitect;
use App\Models\AgentGoal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Accepts a natural language business objective, delegates to the
 * GoalArchitect agent to translate it into a structured goal definition,
 * and persists the resulting AgentGoal record.
 *
 * Used by CameronChat to create goals on behalf of the user.
 */
class CreateGoalFromDescription extends AbstractAgentTool
{
    protected bool $requiresApproval = false;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Create a new monitoring goal from a context summary of what the user wants to achieve. The GoalArchitect will generate the goal name and full definition from the context.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{context: string}  $arguments
     * @return array{success: bool, goal_id: int|null, name: string, definition: array<string, mixed>}
     */
    public function execute(array $arguments): array
    {
        $architect = $this->shop
            ? new GoalArchitect($this->shop)
            : new GoalArchitect;

        $response = $architect->prompt($arguments['context']);

        $definition = [
            'sensor_tool_class' => $response['sensor_tool_class'],
            'sensor_arguments' => $response['sensor_arguments'] ?? [],
            'conditions' => $response['conditions'] ?? [],
            'is_one_off' => $response['is_one_off'] ?? false,
            'expires_at' => $response['expires_at'] ?? null,
        ];

        $goal = AgentGoal::query()->create([
            'shop_id' => $this->shop?->id,
            'name' => $response['name'],
            'initial_context' => $response['initial_context'] ?? null,
            ...$definition,
            'is_active' => true,
        ]);

        return [
            'success' => true,
            'goal_id' => $goal->id,
            'name' => $response['name'],
            'definition' => $definition,
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'context' => $schema->string()->required(),
        ];
    }
}
