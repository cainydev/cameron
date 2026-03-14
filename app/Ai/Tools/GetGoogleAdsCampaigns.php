<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Services\GoogleApiService;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Stringable;

/**
 * Retrieves a list of Google Ads campaigns for the given customer ID.
 */
class GetGoogleAdsCampaigns extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List Google Ads campaigns (id, name, status, budget) for a given customer ID.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{customerId: string, limit?: int}  $arguments
     * @return array<int, array{id: string, name: string, status: string}>
     */
    public function execute(array $arguments): array
    {
        $limit = $arguments['limit'] ?? 100;
        $customerId = $arguments['customerId'];

        $service = new GoogleApiService(Auth::user());
        $adsClient = $service->makeAdsClient();

        $query = "SELECT campaign.id, campaign.name, campaign.status, campaign_budget.amount_micros
                  FROM campaign
                  ORDER BY campaign.id
                  LIMIT {$limit}";

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $response = $adsClient->getGoogleAdsServiceClient()->search($request);

        $campaigns = [];

        foreach ($response->iterateAllElements() as $row) {
            $campaign = $row->getCampaign();
            $budget = $row->getCampaignBudget();

            $campaigns[] = [
                'id' => $campaign->getId(),
                'name' => $campaign->getName(),
                'status' => $campaign->getStatus(),
                'budget_micros' => $budget?->getAmountMicros(),
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
            'customerId' => $schema->string()->required(),
            'limit' => $schema->integer(),
        ];
    }
}
