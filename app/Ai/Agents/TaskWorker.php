<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\AddGoalMemory;
use App\Ai\Tools\GetUnderperformingSearchTerms;
use App\Ai\Tools\MarkTaskAsResolved;
use App\Ai\Tools\PauseGoogleAdCampaign;
use App\Ai\Tools\UpdateAdsCampaignStatus;
use App\Ai\Tools\UpdateKeywordBid;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The Background Fixer — an autonomous worker that analyzes failing goal
 * contexts and uses tools to resolve the issue.
 *
 * Signals completion by calling the MarkTaskAsResolved tool rather than
 * returning structured output, giving it a multi-step tool-use loop.
 */
#[MaxSteps(1)]
#[Timeout(120)]
class TaskWorker implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public string $goalContext,
        public int $taskId,
        public ?string $urgencyDeadline = null,
        public ?string $initialContext = null,
        public array $activeMemories = [],
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $instructions = <<<PROMPT
        You are an autonomous background worker for an e-commerce management system.
        The current task ID is {$this->taskId}.

        Your job:
        1. Analyze the goal context provided to understand what metric has failed and why.
        2. Use your available tools to take corrective action (e.g., pause underperforming campaigns).
        3. When you have finished taking all corrective actions, you MUST call MarkTaskAsResolved with the task_id and a summary of everything you did.
        4. If a tool requires human approval, acknowledge that the action is queued and then call MarkTaskAsResolved with a summary noting which actions are pending approval.

        Be direct and efficient. Do not ask questions — take action.
        If you discover a valuable root cause or take an action that future workers investigating this goal should know about, call AddGoalMemory before calling MarkTaskAsResolved. Set valid_for_hours based on how long this fact will remain true (e.g., 24h for today's finding, 168h for a week-long structural issue).
        PROMPT;

        if ($this->urgencyDeadline) {
            $instructions .= "\n\nURGENT: There is a strict deadline. {$this->urgencyDeadline} "
                .'Prioritize speed and act with extreme urgency. Take the most impactful corrective actions first.';
        }

        if ($this->initialContext) {
            $instructions .= "\n\n## Goal Context from Strategist\n{$this->initialContext}";
        }

        if ($this->activeMemories !== []) {
            $instructions .= "\n\n## Shared Memory from Previous Workers\n";
            foreach ($this->activeMemories as $memory) {
                $instructions .= "- {$memory}\n";
            }
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
        return [
            (new PauseGoogleAdCampaign)->forTask($this->taskId),
            (new UpdateAdsCampaignStatus)->forTask($this->taskId),
            (new AddGoalMemory)->forTask($this->taskId),
            (new MarkTaskAsResolved)->forTask($this->taskId),
            new GetUnderperformingSearchTerms,
            new UpdateKeywordBid,
        ];
    }
}
