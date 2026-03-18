<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Ai\Concerns\FormatsToolOutput;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves keyword-level performance metrics from Google Ads.
 */
#[Category(ToolCategory::GoogleAds)]
class GetKeywordPerformance extends AbstractAgentTool
{
    use FormatsToolOutput;

    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Keyword Performance';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch keyword-level metrics including clicks, impressions, cost, conversions, match type, and CPC bid. Optionally filter by campaign or ad group.';
    }

    /**
     * @param  array{startDate: string, endDate: string, campaignId?: string, adGroupId?: string, limit?: int}  $arguments
     * @return array<int, array{keyword: string, matchType: string, adGroup: string, clicks: int, impressions: int, cost: float, conversions: float, cpcBid: float}>
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $limit = $arguments['limit'] ?? 100;

        $whereClause = "WHERE segments.date BETWEEN '{$arguments['startDate']}' AND '{$arguments['endDate']}'";

        if (! empty($arguments['campaignId'])) {
            $whereClause .= " AND campaign.id = {$arguments['campaignId']}";
        }

        if (! empty($arguments['adGroupId'])) {
            $whereClause .= " AND ad_group.id = {$arguments['adGroupId']}";
        }

        $query = "SELECT ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type,
                         ad_group.name, metrics.clicks, metrics.impressions, metrics.cost_micros,
                         metrics.conversions, ad_group_criterion.cpc_bid_micros
                  FROM keyword_view
                  {$whereClause}
                  ORDER BY metrics.cost_micros DESC
                  LIMIT {$limit}";

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $response = $this->googleApiService()->makeAdsClient()
            ->getGoogleAdsServiceClient()->search($request);

        $results = [];

        foreach ($response->iterateAllElements() as $row) {
            $criterion = $row->getAdGroupCriterion();
            $results[] = [
                'keyword' => $criterion->getKeyword()->getText(),
                'matchType' => $this->matchTypeLabel($criterion->getKeyword()->getMatchType()),
                'adGroup' => $row->getAdGroup()->getName(),
                'clicks' => $row->getMetrics()->getClicks(),
                'impressions' => $row->getMetrics()->getImpressions(),
                'cost' => $this->microsToCurrency($row->getMetrics()->getCostMicros()),
                'conversions' => round($row->getMetrics()->getConversions(), 2),
                'cpcBid' => $this->microsToCurrency($criterion->getCpcBidMicros()),
            ];
        }

        return $results;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'startDate' => $schema->string()->required(),
            'endDate' => $schema->string()->required(),
            'campaignId' => $schema->string(),
            'adGroupId' => $schema->string(),
            'limit' => $schema->integer(),
        ];
    }
}
