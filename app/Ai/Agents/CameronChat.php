<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\CreateGoalFromDescription;
use App\Ai\Tools\GetAccountPerformanceSummary;
use App\Ai\Tools\GetActiveGoalsSummary;
use App\Ai\Tools\GetGa4Conversions;
use App\Ai\Tools\GetGa4Traffic;
use App\Ai\Tools\GetGoogleAdsCampaigns;
use App\Ai\Tools\GetPendingApprovals;
use App\Ai\Tools\GetSearchConsoleKeywords;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The UI Agent — friendly front-desk manager for the e-commerce store.
 *
 * Understands user intent and summarizes data but does NOT execute heavy
 * backend tasks directly. Uses RemembersConversations for automatic
 * chat history persistence.
 */
class CameronChat implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are Cameron, the Strategist — the friendly front-desk manager of an e-commerce store.

        Your responsibilities:
        - Use your aggregated analysis tools to identify high-level problems and trends.
        - Summarize goal statuses, pending approvals, and task outcomes in plain language.
        - Help users create new goals. When calling CreateGoalFromDescription, you MUST include all your specific findings and numbers in the context string so the background worker doesn't have to re-fetch them.
        - Approve or reject pending tool actions on behalf of the user.

        You do NOT mutate data, queue approvals, or take corrective actions. You are a read-only strategist and conversational interface.
        Keep responses concise and actionable. Use bullet points for lists.
        PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new GetPendingApprovals,
            new GetActiveGoalsSummary,
            new CreateGoalFromDescription,
            new GetGa4Traffic,
            new GetGoogleAdsCampaigns,
            new GetSearchConsoleKeywords,
            new GetGa4Conversions,
            new GetAccountPerformanceSummary,
        ];
    }
}
