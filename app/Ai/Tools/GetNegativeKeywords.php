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
 * Retrieves campaign-level negative keywords from Google Ads.
 */
#[Category(ToolCategory::GoogleAds)]
class GetNegativeKeywords extends AbstractAgentTool
{
    use FormatsToolOutput;

    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        if (! empty($arguments['campaignId'])) {
            return "Negative Keywords (Campaign #{$arguments['campaignId']})";
        }

        return 'Negative Keywords';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve campaign-level negative keywords for a given campaign.';
    }

    /**
     * @param  array{campaignId: string, limit?: int}  $arguments
     * @return array<int, array{keywordText: string, matchType: int}>
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $limit = $arguments['limit'] ?? 100;

        $query = "SELECT campaign_criterion.keyword.text, campaign_criterion.keyword.match_type
                  FROM campaign_criterion
                  WHERE campaign.id = {$arguments['campaignId']}
                    AND campaign_criterion.type = 'KEYWORD'
                    AND campaign_criterion.negative = TRUE
                  LIMIT {$limit}";

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $response = $this->googleApiService()->makeAdsClient()
            ->getGoogleAdsServiceClient()->search($request);

        $results = [];

        foreach ($response->iterateAllElements() as $row) {
            $keyword = $row->getCampaignCriterion()->getKeyword();
            $results[] = [
                'keyword' => $keyword->getText(),
                'matchType' => $this->matchTypeLabel($keyword->getMatchType()),
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
            'campaignId' => $schema->string()->required(),
            'limit' => $schema->integer(),
        ];
    }
}
