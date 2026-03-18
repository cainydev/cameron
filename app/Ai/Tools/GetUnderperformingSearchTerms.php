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
 * Finds Google Ads search terms that spent money without generating conversions.
 */
#[Category(ToolCategory::GoogleAds)]
class GetUnderperformingSearchTerms extends AbstractAgentTool
{
    use FormatsToolOutput;

    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Underperforming Search Terms';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Find Google Ads search terms that spent money in the last 30 days without generating any conversions. Use this to identify wasted spend.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, array{searchTerm: string, cost: float, conversions: float}>
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $service = $this->googleApiService();
        $adsClient = $service->makeAdsClient();

        $limit = $arguments['limit'] ?? 100;

        $query = "SELECT search_term_view.search_term, metrics.cost_micros, metrics.conversions
                  FROM search_term_view
                  WHERE segments.date DURING LAST_30_DAYS
                    AND metrics.cost_micros > 10000000
                    AND metrics.conversions = 0
                  ORDER BY metrics.cost_micros DESC
                  LIMIT {$limit}";

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $response = $adsClient->getGoogleAdsServiceClient()->search($request);

        $terms = [];

        foreach ($response->iterateAllElements() as $row) {
            $terms[] = [
                'searchTerm' => $row->getSearchTermView()->getSearchTerm(),
                'cost' => $this->microsToCurrency($row->getMetrics()->getCostMicros()),
                'conversions' => round($row->getMetrics()->getConversions(), 2),
            ];
        }

        return $terms;
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
