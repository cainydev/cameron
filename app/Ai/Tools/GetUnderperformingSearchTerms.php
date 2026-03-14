<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Services\GoogleApiService;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Stringable;

/**
 * Finds Google Ads search terms that spent money without generating conversions.
 */
class GetUnderperformingSearchTerms extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

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
     * @param  array{customerId: string}  $arguments
     * @return array<int, array{searchTerm: string, costMicros: int, conversions: float}>
     */
    public function execute(array $arguments): array
    {
        $service = new GoogleApiService(Auth::user());
        $adsClient = $service->makeAdsClient();

        $query = 'SELECT search_term_view.search_term, metrics.cost_micros, metrics.conversions
                  FROM search_term_view
                  WHERE segments.date DURING LAST_30_DAYS
                    AND metrics.cost_micros > 10000000
                    AND metrics.conversions = 0
                  ORDER BY metrics.cost_micros DESC
                  LIMIT 20';

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $arguments['customerId'],
            'query' => $query,
        ]);

        $response = $adsClient->getGoogleAdsServiceClient()->search($request);

        $terms = [];

        foreach ($response->iterateAllElements() as $row) {
            $terms[] = [
                'searchTerm' => $row->getSearchTermView()->getSearchTerm(),
                'costMicros' => $row->getMetrics()->getCostMicros(),
                'conversions' => $row->getMetrics()->getConversions(),
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
            'customerId' => $schema->string()->required(),
        ];
    }
}
