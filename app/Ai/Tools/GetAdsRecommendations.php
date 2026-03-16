<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves Google Ads recommendations for budget, keyword, and bid suggestions.
 */
#[Category(ToolCategory::GoogleAds)]
class GetAdsRecommendations extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Ads Recommendations';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve Google Ads recommendations (budget, keyword, bid suggestions). Optionally filter by recommendation types.';
    }

    /**
     * @param  array{types?: array<string>, limit?: int}  $arguments
     * @return array<int, array{type: int, campaignId: string|null, campaignName: string|null, impact: string|null}>
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $limit = $arguments['limit'] ?? 10;

        $whereClause = 'WHERE recommendation.type != UNKNOWN';

        if (! empty($arguments['types'])) {
            $types = implode("', '", $arguments['types']);
            $whereClause = "WHERE recommendation.type IN ('{$types}')";
        }

        $query = "SELECT recommendation.type, recommendation.campaign, recommendation.impact
                  FROM recommendation
                  {$whereClause}
                  LIMIT {$limit}";

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $response = $this->googleApiService()->makeAdsClient()
            ->getGoogleAdsServiceClient()->search($request);

        $results = [];

        foreach ($response->iterateAllElements() as $row) {
            $rec = $row->getRecommendation();
            $results[] = [
                'type' => $rec->getType(),
                'campaignId' => $rec->getCampaign() ?: null,
                'impact' => $rec->getImpact() ? json_encode($rec->getImpact()->serializeToJsonString()) : null,
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
            'types' => $schema->array()->items($schema->string()),
            'limit' => $schema->integer(),
        ];
    }
}
