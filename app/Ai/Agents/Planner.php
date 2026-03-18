<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\AgentRole;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The Planner — a tool-less agent that analyzes failing goal context and
 * produces a structured, sequential execution plan for Specialist agents.
 */
#[Model('gemini-3.1-flash-lite-preview')]
#[Timeout(60)]
class Planner implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public string $objective,
        public array $sensorData,
        public array $failedConditions,
        public ?string $shopContext = null,
        public ?string $initialContext = null,
        public ?string $revisionHints = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $roleDescriptions = $this->buildRoleDescriptions();

        $instructions = <<<PROMPT
        You are a planning agent for an e-commerce management system. You do NOT execute tools or take actions. Your sole job is to produce a structured, sequential execution plan that Specialist agents will carry out.

        ## Available Specialist Roles

        {$roleDescriptions}

        ## Planning Rules

        1. Each step must specify exactly ONE specialist role and a clear action instruction.
        2. Steps execute sequentially — earlier steps complete before later ones start.
        3. Use the `depends_on` field (step order number) only when a step explicitly needs data from a prior step. Set to null otherwise.
        4. Analytics steps should come FIRST to gather data before taking action.
        5. Keep plans concise — 2-5 steps for most goals. Never exceed 8 steps.
        6. For read-only investigations, set on_failure to "retry". For mutating actions, set on_failure to "escalate".
        7. Write action instructions as if speaking to the specialist: include specific metrics, thresholds, campaign IDs, and date ranges from the sensor data.
        8. Do NOT include steps that the specialists cannot perform. Each specialist only has tools from their assigned categories.

        ## Context

        **Objective:** {$this->objective}

        **Sensor Data (latest readings):**
        ```json
        {$this->formatJson($this->sensorData)}
        ```

        **Failed Conditions:**
        ```json
        {$this->formatJson($this->failedConditions)}
        ```
        PROMPT;

        if ($this->shopContext) {
            $instructions .= "\n\n**Shop Context:**\n{$this->shopContext}";
        }

        if ($this->initialContext) {
            $instructions .= "\n\n**Strategist Notes:**\n{$this->initialContext}";
        }

        if ($this->revisionHints) {
            $instructions .= "\n\n**REVISION REQUIRED:** A previous plan failed. Here is what went wrong and what to change:\n{$this->revisionHints}";
        }

        return $instructions;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'steps' => $schema->array()->required()->min(1)->max(8)->items(
                $schema->object([
                    'order' => $schema->integer()->required()->description('Sequential step number starting at 1'),
                    'role' => $schema->string()->required()->enum(AgentRole::class)->description('The specialist role to execute this step'),
                    'action' => $schema->string()->required()->description('Clear instruction for the specialist, including specific metrics, thresholds, and identifiers'),
                    'depends_on' => $schema->integer()->nullable()->description('The order number of a step this depends on, or null'),
                    'on_failure' => $schema->string()->required()->enum(['retry', 'escalate', 'halt'])->description('What to do if this step fails'),
                ]),
            ),
        ];
    }

    protected function buildRoleDescriptions(): string
    {
        $descriptions = [];

        foreach (AgentRole::cases() as $role) {
            if ($role === AgentRole::System) {
                continue;
            }

            $categories = implode(', ', array_map(
                fn ($cat) => $cat->label(),
                $role->toolCategories(),
            ));

            $descriptions[] = "- **{$role->label()}** (`{$role->value}`): Has access to {$categories} tools.";
        }

        return implode("\n", $descriptions);
    }

    protected function formatJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
