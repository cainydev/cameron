<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\Shop;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The Translator Agent — converts natural language business objectives
 * into strict JSON schemas for the AgentGoals database table.
 *
 * Returns structured output matching the AgentGoal creation schema.
 */
class GoalArchitect implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(public ?Shop $shop = null) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $shopContext = '';

        if ($this->shop) {
            $now = now()->setTimezone($this->shop->timezone ?? 'UTC');

            $lines = array_filter([
                "- Shop: {$this->shop->name}",
                $this->shop->currency ? "- Currency: {$this->shop->currency}" : null,
                $this->shop->target_roas ? "- Target ROAS: {$this->shop->target_roas}" : null,
                $this->shop->base_instructions ? "- Instructions: {$this->shop->base_instructions}" : null,
                "- Current date/time: {$now->toDateTimeString()} ({$this->shop->timezone})",
            ]);

            $shopContext = "\n\n## Shop Context\n".implode("\n", $lines);
        }

        return <<<PROMPT
        You are a Goal Architect. Your sole purpose is to translate a natural language
        context summary into a strict, machine-readable JSON goal definition.{$shopContext}

        Rules:
        - name must be a short, human-readable title for the goal (5 words or fewer).
        - sensor_tool_class must be a fully qualified PHP class name (e.g., "App\Ai\Tools\GoogleAdsSensor").
        - sensor_arguments must be a JSON object with any extra parameters the sensor tool needs (do NOT include propertyId, customerId, or siteUrl — those are provided automatically by the shop configuration).
        - conditions must be an array of objects, each with "metric" (string), "operator" (one of: >, >=, <, <=, ==, !=), and "value" (numeric).
        - is_one_off should be true only if the user describes a milestone or one-time target.
        - expires_at should be an ISO 8601 timestamp if the user mentions a deadline, otherwise null.
        - initial_context must contain all specific findings, numbers, and data points Cameron gathered that prompted this goal. This will be used to brief the background worker so it doesn't have to re-fetch the same data.

        Be precise. Do not add extra fields. Do not explain your reasoning — only return the structured output.
        PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
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
            'initial_context' => $schema->string()->required(),
        ];
    }
}
