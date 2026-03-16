<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves ad group performance metrics from Google Ads.
 */
#[Category(ToolCategory::GoogleAds)]
class GetAdGroupPerformance extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Ad Group Performance';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch ad group performance metrics (clicks, impressions, cost, conversions) for a date range. Optionally filter by campaign ID.';
    }

    /**
     * @param  array{startDate: string, endDate: string, campaignId?: string, limit?: int}  $arguments
     * @return array<int, array{adGroupId: string, adGroupName: string, campaignName: string, clicks: int, impressions: int, costMicros: int, conversions: float}>
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $limit = $arguments['limit'] ?? 20;

        $whereClause = "WHERE segments.date BETWEEN '{$arguments['startDate']}' AND '{$arguments['endDate']}'";

        if (! empty($arguments['campaignId'])) {
            $whereClause .= " AND campaign.id = {$arguments['campaignId']}";
        }

        $query = "SELECT ad_group.id, ad_group.name, campaign.name,
                         metrics.clicks, metrics.impressions, metrics.cost_micros, metrics.conversions
                  FROM ad_group
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
            $results[] = [
                'adGroupId' => $row->getAdGroup()->getId(),
                'adGroupName' => $row->getAdGroup()->getName(),
                'campaignName' => $row->getCampaign()->getName(),
                'clicks' => $row->getMetrics()->getClicks(),
                'impressions' => $row->getMetrics()->getImpressions(),
                'costMicros' => $row->getMetrics()->getCostMicros(),
                'conversions' => $row->getMetrics()->getConversions(),
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
            'limit' => $schema->integer(),
        ];
    }
}
