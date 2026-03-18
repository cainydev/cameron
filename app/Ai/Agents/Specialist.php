<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\ToolRegistry;
use App\Enums\AgentRole;
use App\Models\Shop;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * A parameterized Specialist agent — constrained to a single domain role.
 *
 * Receives its tool set from the AgentRole, its action instruction from the
 * plan step, and working memory from prior steps. Mirrors the TaskWorker
 * pattern but with narrower scope.
 */
#[Model('gemini-3.1-flash-lite-preview')]
#[MaxSteps(1)]
#[Timeout(120)]
class Specialist implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public AgentRole $role,
        public Shop $shop,
        public string $stepInstruction,
        public int $taskId,
        public array $workingMemory = [],
        public ?string $goalContext = null,
        public ?string $urgencyDeadline = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $now = now()->setTimezone($this->shop->timezone ?? 'UTC');
        $currentDateTime = $now->toDateTimeString().' ('.($this->shop->timezone ?? 'UTC').')';

        $instructions = "You are a {$this->role->label()} for an e-commerce management system.\n";
        $instructions .= "Current date/time: {$currentDateTime}\n\n";

        if ($this->role === AgentRole::Ads) {
            $adsCore = File::get(resource_path('prompts/ads_core.md'));
            $instructions .= "{$adsCore}\n\n";
        }

        $instructions .= "## Your Task\n{$this->stepInstruction}\n\n";

        $instructions .= "## Rules\n";
        $instructions .= "- Focus exclusively on the task described above.\n";
        $instructions .= "- Use your tools to gather data and take action as instructed.\n";
        $instructions .= "- When you have completed all actions for this step, output a concise summary of what you found or did.\n";
        $instructions .= "- If a tool requires human approval, acknowledge that the action is queued and summarize what was attempted.\n";
        $instructions .= "- Be direct and efficient. Do not ask questions — take action.\n";

        if ($this->workingMemory !== []) {
            $instructions .= "\n## Context from Prior Steps\n";
            foreach ($this->workingMemory as $stepKey => $data) {
                $summary = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $instructions .= "**{$stepKey}:** {$summary}\n\n";
            }
        }

        if ($this->goalContext) {
            $instructions .= "\n## Goal Sensor Data\n```json\n{$this->goalContext}\n```\n";
        }

        if ($this->urgencyDeadline) {
            $instructions .= "\nURGENT: There is a strict deadline. {$this->urgencyDeadline} "
                .'Prioritize speed and act with extreme urgency.';
        }

        return $instructions;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return app(ToolRegistry::class)
            ->forShop($this->shop)
            ->forTask($this->taskId)
            ->inCategories($this->role->toolCategories())
            ->resolve();
    }
}
