<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves a list of Google Ads campaigns for the shop's customer ID.
 */
class GetGoogleAdsCampaigns extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List Google Ads campaigns ordered by spend in the last 30 days. Defaults to 10 results. Increase limit to fetch more campaigns.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{limit?: int}  $arguments
     * @return array<int, array{id: string, name: string, status: string, cost_micros: int}>
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $limit = $arguments['limit'] ?? 10;

        $service = $this->googleApiService();
        $adsClient = $service->makeAdsClient();

        $query = "SELECT campaign.id, campaign.name, campaign.status, metrics.cost_micros
                  FROM campaign
                  WHERE segments.date DURING LAST_30_DAYS
                  ORDER BY metrics.cost_micros DESC
                  LIMIT {$limit}";

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $response = $adsClient->getGoogleAdsServiceClient()->search($request);

        $campaigns = [];

        foreach ($response->iterateAllElements() as $row) {
            $campaign = $row->getCampaign();

            $campaigns[] = [
                'id' => $campaign->getId(),
                'name' => $campaign->getName(),
                'status' => $campaign->getStatus(),
                'cost_micros' => $row->getMetrics()->getCostMicros(),
            ];
        }

        return $campaigns;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer(),
        ];
    }
}
