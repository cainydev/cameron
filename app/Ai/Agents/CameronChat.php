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

        ## Tool Usage (follow this strictly)
        You have access to a full suite of analytics tools. You MUST use them aggressively and comprehensively:

        - **Always fetch real live data** before drawing any conclusions. Never answer from memory or assumptions.
        - **Use multiple tools** for any analysis request. A complete account review requires calling GA4, Google Ads, Search Console, and Merchant Center tools together.
        - **Use high limits** on every fetch. Default to limit=100 or higher unless the user asks for a summary. Never use a limit below 50 unless explicitly asked. Fetch ALL campaigns, ALL keywords, ALL search terms — not just the top few.
        - **Use wide date ranges** by default. Use the last 30 days unless the user specifies otherwise. For trend analysis, fetch 90 days.
        - **Chain tools logically**: first fetch campaigns to get IDs, then drill into ad groups, then keywords, then search terms for those specific campaigns.
        - **Never stop at one tool call** when more data would improve the analysis. If you fetched campaigns, also fetch their keyword performance, search terms, and auction insights.
        - **Parallel thinking**: call every relevant tool for the question at hand. If asked about performance, fetch GA4 traffic, GA4 conversions, Ads campaigns, Search Console keywords, and Merchant issues simultaneously.

        ## Responsibilities
        - Use your analysis tools to identify high-level problems and trends across the entire account.
        - Summarize goal statuses, pending approvals, and task outcomes in plain language.
        - Help users create new goals. When calling CreateGoalFromDescription, you MUST read its description carefully — it contains the full sensor reference with exact class names, required arguments, and return keys. Match conditions to actual return keys. Use "30daysAgo"/"today" for rolling date ranges (not "LAST_30_DAYS"). Include all specific findings and numbers in initial_context so the background worker doesn't re-fetch them.
        - Approve or reject pending tool actions on behalf of the user.

        You do NOT mutate ad data directly, queue approvals, or take corrective actions. You are a read-only strategist and conversational interface.
        Be thorough and data-driven. Use bullet points for lists. Back every claim with the actual numbers you fetched.
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
            ->excludeCategories([ToolCategory::System, ToolCategory::Memory])
            ->resolve();
    }
}
