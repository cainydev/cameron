<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves Google Ads auction insights to benchmark against competitors.
 */
#[Category(ToolCategory::GoogleAds)]
class GetAuctionInsights extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Auction Insights';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve auction insights to compare impression share, overlap rate, and position above rate against competitors. Optionally filter by campaign ID.';
    }

    /**
     * @param  array{startDate: string, endDate: string, campaignId?: string, limit?: int}  $arguments
     * @return array<int, array{domain: string, impressionShare: float, overlapRate: float, positionAboveRate: float, topOfPageRate: float, absTopOfPageRate: float}>
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $limit = $arguments['limit'] ?? 25;

        $whereClause = "WHERE segments.date BETWEEN '{$arguments['startDate']}' AND '{$arguments['endDate']}'";

        if (! empty($arguments['campaignId'])) {
            $whereClause .= " AND campaign.id = {$arguments['campaignId']}";
        }

        $query = "SELECT auction_insight_performance_view.display_name,
                         metrics.auction_insight_search_impression_share,
                         metrics.auction_insight_search_overlap_rate,
                         metrics.auction_insight_search_position_above_rate,
                         metrics.auction_insight_search_top_impression_percentage,
                         metrics.auction_insight_search_abs_top_impression_percentage
                  FROM auction_insight_performance_view
                  {$whereClause}
                  ORDER BY metrics.auction_insight_search_impression_share DESC
                  LIMIT {$limit}";

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $response = $this->googleApiService()->makeAdsClient()
            ->getGoogleAdsServiceClient()->search($request);

        $results = [];

        foreach ($response->iterateAllElements() as $row) {
            $view = $row->getAuctionInsightPerformanceView();
            $metrics = $row->getMetrics();

            $results[] = [
                'domain' => $view->getDisplayName(),
                'impressionShare' => $metrics->getAuctionInsightSearchImpressionShare(),
                'overlapRate' => $metrics->getAuctionInsightSearchOverlapRate(),
                'positionAboveRate' => $metrics->getAuctionInsightSearchPositionAboveRate(),
                'topOfPageRate' => $metrics->getAuctionInsightSearchTopImpressionPercentage(),
                'absTopOfPageRate' => $metrics->getAuctionInsightSearchAbsTopImpressionPercentage(),
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
