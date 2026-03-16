<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\ToolRegistry;
use App\Enums\ToolCategory;
use App\Models\Shop;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Attributes\Model;
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
#[Model('gemini-3.1-flash-lite-preview')]
class CameronChat implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public Shop $shop) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $now = now()->setTimezone($this->shop->timezone ?? 'UTC');

        $shopContext = implode("\n", array_filter([
            "- Shop: {$this->shop->name}",
            $this->shop->url ? "- Website: {$this->shop->url}" : null,
            "- Timezone: {$this->shop->timezone}",
            "- Currency: {$this->shop->currency}",
            "- Current date/time: {$now->toDateTimeString()} ({$this->shop->timezone})",
            $this->shop->target_roas ? "- Target ROAS: {$this->shop->target_roas}" : null,
            $this->shop->base_instructions ? "- Instructions: {$this->shop->base_instructions}" : null,
            $this->shop->brand_guidelines ? "- Brand guidelines: {$this->shop->brand_guidelines}" : null,
        ]));

        $adsCore = File::get(resource_path('prompts/ads_core.md'));

        return <<<PROMPT
        {$adsCore}

        ## Shop Context
        {$shopContext}

        ## Memory Protocol (follow this strictly)
        You have a persistent long-term memory that survives across conversations. Use it well:
        - **At the start of every conversation**, call RecallMemories to surface prior findings before fetching any live data. This avoids redundant API calls and gives you historical context.
        - **Whenever you discover a meaningful finding** — a trend, a spike, a structural issue, a campaign problem, a product insight, or anything the user mentions that should be remembered — call RememberFinding immediately. Include the date, specific numbers, and why it matters.
        - **When new data contradicts a prior memory**, call UpdateMemory to correct it rather than leaving stale information.
        - **When a memory is clearly no longer relevant** (e.g., a campaign that no longer exists, a seasonal issue that has passed), call ForgetMemory.
        - Categories: performance, campaign, seo, conversion, budget, audience, product, general.

        ## Responsibilities
        - Use your analysis tools to identify high-level problems and trends.
        - Summarize goal statuses, pending approvals, and task outcomes in plain language.
        - Help users create new goals. When calling CreateGoalFromDescription, include all specific findings and numbers so the background worker doesn't re-fetch them.
        - Approve or reject pending tool actions on behalf of the user.

        You do NOT mutate ad data directly, queue approvals, or take corrective actions. You are a read-only strategist and conversational interface.
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
        return app(ToolRegistry::class)
            ->forShop($this->shop)
            ->excludeApprovalRequired()
            ->excludeCategories([ToolCategory::System])
            ->resolve();
    }
}
