<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use App\Models\AgentGoal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Creates a new monitoring goal directly from the fields Cameron provides.
 *
 * Cameron is responsible for determining the correct sensor class, conditions,
 * and frequency based on the conversation context. No secondary agent is needed.
 */
#[Category(ToolCategory::Goals)]
class CreateGoalFromDescription extends AbstractAgentTool
{
    protected bool $requiresApproval = false;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        if (! empty($arguments['name'])) {
            return "Create Goal: {$arguments['name']}";
        }

        return 'Create Goal';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return <<<'DESC'
        Create a new monitoring goal. You must populate all fields yourself based on the conversation.

        sensor_tool_class: fully qualified PHP class (e.g. "App\Ai\Tools\GetGa4Conversions"). Use an existing tool that returns the metric you want to monitor. Pass only extra parameters in sensor_arguments — propertyId, customerId, and siteUrl are injected automatically.
        conditions: array of {metric, operator, value} objects. metric must match a key returned by the sensor tool. operator is one of: >, >=, <, <=, ==, !=.
        check_frequency_minutes: how often to re-evaluate. 15–30 for near-real-time, 60 for most goals, 1440 for daily. Default 60.
        is_one_off: true only for one-time milestones.
        expires_at: ISO 8601 deadline, or omit entirely.
        initial_context: paste in the specific numbers and findings from this conversation so the background worker doesn't re-fetch them.
        DESC;
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{name: string, sensor_tool_class: string, sensor_arguments: array<string, mixed>, conditions: array<int, array{metric: string, operator: string, value: float|int}>, is_one_off: bool, expires_at?: string|null, check_frequency_minutes: int, initial_context: string}  $arguments
     * @return array{success: bool, goal_id: int}
     */
    public function execute(array $arguments): array
    {
        $goal = AgentGoal::query()->create([
            'shop_id' => $this->shop?->id,
            'name' => $arguments['name'],
            'sensor_tool_class' => $arguments['sensor_tool_class'],
            'sensor_arguments' => $arguments['sensor_arguments'] ?? [],
            'conditions' => $arguments['conditions'],
            'is_one_off' => $arguments['is_one_off'] ?? false,
            'expires_at' => $arguments['expires_at'] ?? null,
            'check_frequency_minutes' => $arguments['check_frequency_minutes'] ?? 60,
            'initial_context' => $arguments['initial_context'],
            'is_active' => true,
        ]);

        return [
            'success' => true,
            'goal_id' => $goal->id,
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'sensor_tool_class' => $schema->string()->required(),
            'sensor_arguments' => $schema->object()->required(),
            'conditions' => $schema->array()->items(
                $schema->object([
                    'metric' => $schema->string()->required(),
                    'operator' => $schema->string()->enum(['>', '>=', '<', '<=', '==', '!='])->required(),
                    'value' => $schema->number()->required(),
                ])
            )->required(),
            'is_one_off' => $schema->boolean()->required(),
            'expires_at' => $schema->string(),
            'check_frequency_minutes' => $schema->integer()->required(),
            'initial_context' => $schema->string()->required(),
        ];
    }
}
