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

        ## Field Reference

        sensor_tool_class: fully qualified PHP class name from the sensor reference below.
        sensor_arguments: JSON object with ONLY the arguments listed for that sensor. propertyId, customerId, siteUrl, and merchantId are injected automatically — never include them. Date arguments must use literal dates in YYYY-MM-DD format (e.g. "2026-03-01"), not shorthands like "LAST_30_DAYS". For rolling date ranges, prefer using "startDate":"30daysAgo" and "endDate":"today" — GA4 sensors accept these relative values natively.
        conditions: JSON array of {metric, operator, value} objects. The "metric" must EXACTLY match a return key from the sensor reference below. operator is one of: >, >=, <, <=, ==, !=.
        check_frequency_minutes: how often to re-evaluate. 15–30 for near-real-time, 60 for most goals, 1440 for daily. Default 60.
        is_one_off: true only for one-time milestones.
        expires_at: ISO 8601 deadline, or omit entirely.
        initial_context: paste in the specific numbers and findings from this conversation so the background worker doesn't re-fetch them.

        ## Sensor Reference (use these EXACTLY)

        App\Ai\Tools\GetGa4Traffic
          args: startDate (required), endDate (required)
          returns per row: sessions, pageViews

        App\Ai\Tools\GetGa4Conversions
          args: startDate (required), endDate (required), eventName
          returns per row: eventName, conversions, eventCount

        App\Ai\Tools\GetGa4TrafficSources
          args: startDate (required), endDate (required)
          returns per row: channel, sessions, engagementRate

        App\Ai\Tools\GetGa4ECommerceItemSales
          args: startDate (required), endDate (required), limit
          returns per row: itemName, itemRevenue, itemsPurchased

        App\Ai\Tools\GetGa4LandingPagePerformance
          args: startDate (required), endDate (required), limit
          returns per row: landingPage, sessions, bounceRate, engagementRate

        App\Ai\Tools\GetGoogleAdsCampaigns
          args: limit
          returns per row: id, name, status, type, biddingStrategy, dailyBudget, targetRoas, cost, impressions, clicks, conversions, impressionShare, budgetLostIS, rankLostIS

        App\Ai\Tools\GetAdGroupPerformance
          args: startDate (required), endDate (required), campaignId, limit
          returns per row: adGroupId, adGroupName, campaignName, clicks, impressions, cost, conversions

        App\Ai\Tools\GetKeywordPerformance
          args: startDate (required), endDate (required), campaignId, adGroupId, limit
          returns per row: keyword, matchType, adGroup, clicks, impressions, cost, conversions, cpcBid

        App\Ai\Tools\GetSearchConsoleKeywords
          args: startDate (required), endDate (required), limit
          returns per row: query, clicks, impressions, ctr, position

        App\Ai\Tools\GetSearchConsolePages
          args: startDate (required), endDate (required), limit
          returns per row: page, clicks, impressions, ctr, position

        App\Ai\Tools\GetSearchConsoleCountries
          args: startDate (required), endDate (required), limit
          returns per row: country, clicks, impressions, ctr, position

        App\Ai\Tools\GetSearchConsoleDevices
          args: startDate (required), endDate (required)
          returns per row: device, clicks, impressions, ctr, position

        App\Ai\Tools\GetAccountPerformanceSummary
          args: startDate (required), endDate (required)
          returns: sessions, pageViews, totalSpendMicros, totalClicks, totalConversions, period

        App\Ai\Tools\GetMerchantProductIssues
          args: pageSize, pageToken
          returns: merchantId, products (array with id, title, issues)

        App\Ai\Tools\GetMerchantProducts
          args: pageSize, pageToken
          returns: merchantId, products (array with id, offerId, title, brand, availability, price, feedLabel)

        App\Ai\Tools\GetUnderperformingSearchTerms
          args: limit
          returns per row: searchTerm, cost, conversions
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
        $sensorArguments = is_string($arguments['sensor_arguments'] ?? null)
            ? json_decode($arguments['sensor_arguments'], true) ?? []
            : ($arguments['sensor_arguments'] ?? []);

        $conditions = is_string($arguments['conditions'] ?? null)
            ? json_decode($arguments['conditions'], true) ?? []
            : ($arguments['conditions'] ?? []);

        $goal = AgentGoal::query()->create([
            'shop_id' => $this->shop?->id,
            'name' => $arguments['name'],
            'sensor_tool_class' => $arguments['sensor_tool_class'],
            'sensor_arguments' => $sensorArguments,
            'conditions' => $conditions,
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
            'sensor_arguments' => $schema->string()->required()->description('JSON object of extra sensor parameters (e.g. {"dateRange":"LAST_30_DAYS"}). Use "{}" if none needed.'),
            'conditions' => $schema->string()->required()->description('JSON array of condition objects. Each object: {"metric":"<key>","operator":"<>|>=|<|<=|==|!=","value":<number>}. Example: [{"metric":"roas","operator":">=","value":3}]'),
            'is_one_off' => $schema->boolean()->required(),
            'expires_at' => $schema->string(),
            'check_frequency_minutes' => $schema->integer()->required(),
            'initial_context' => $schema->string()->required(),
        ];
    }
}
