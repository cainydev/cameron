<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Cross-references search terms against a bad-traffic blocklist to suggest negative keywords.
 */
#[Category(ToolCategory::GoogleAds)]
class SuggestNegativeKeywords extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Search terms matching any of these patterns should be blocked.
     * Covers common irrelevant-intent signals for Kräuter/Naturprodukte shops.
     *
     * @var list<string>
     */
    private const BAD_TRAFFIC_PATTERNS = [
        // Informational / research intent — no purchase intent
        'wikipedia', 'wiki', 'was ist', 'what is', 'definition', 'bedeutung',
        'wie wirkt', 'wirkung von', 'nebenwirkungen', 'side effects',
        'erfahrungen forum', 'forum', 'reddit',

        // DIY / free alternatives
        'selber machen', 'selbst herstellen', 'diy', 'rezept', 'anleitung',
        'kostenlos', 'free', 'gratis', 'umsonst',

        // Medical / prescription signals
        'apotheke rezept', 'verschreibungspflichtig', 'arzt', 'verschreiben',
        'krankenkasse', 'kassenleistung',

        // Competitor brand terms (generic)
        'dm marke', 'rossmann marke', 'amazon basics',

        // Wrong product category signals
        'parfüm', 'parfum', 'aftershave', 'deodorant', 'shampoo rezept',
        'haarfarbe', 'schminke', 'make-up', 'makeup',

        // Job / career
        'job', 'jobs', 'ausbildung', 'praktikum', 'gehalt', 'stellenangebot',

        // Second-hand / used
        'gebraucht', 'used', 'second hand', 'ebay kleinanzeigen', 'kleinanzeigen',

        // Wholesale / B2B at very low relevance
        'großhandel', 'wholesale', 'bulk kaufen', 'palette kaufen',

        // News / study
        'studie', 'studie 2024', 'news', 'aktuell',
    ];

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Suggest Negative Keywords';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Analyse recent search terms and cross-reference them against a bad-traffic blocklist to produce a ranked list of negative keyword suggestions. Use AddNegativeKeyword to apply suggestions after review.';
    }

    /**
     * @param  array{campaignId: string, minCostMicros?: int, limit?: int}  $arguments
     * @return array{campaignId: string, suggestions: array<int, array{searchTerm: string, costMicros: int, clicks: int, conversions: float, matchedPattern: string, suggestedMatchType: string}>}
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $minCostMicros = $arguments['minCostMicros'] ?? 0;
        $limit = $arguments['limit'] ?? 200;

        $query = "SELECT search_term_view.search_term, metrics.cost_micros,
                         metrics.clicks, metrics.conversions
                  FROM search_term_view
                  WHERE campaign.id = {$arguments['campaignId']}
                    AND segments.date DURING LAST_30_DAYS
                    AND metrics.cost_micros >= {$minCostMicros}
                  ORDER BY metrics.cost_micros DESC
                  LIMIT {$limit}";

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $response = $this->googleApiService()->makeAdsClient()
            ->getGoogleAdsServiceClient()->search($request);

        $suggestions = [];

        foreach ($response->iterateAllElements() as $row) {
            $term = $row->getSearchTermView()->getSearchTerm();
            $matchedPattern = $this->matchesBadTraffic($term);

            if ($matchedPattern === null) {
                continue;
            }

            $suggestions[] = [
                'searchTerm' => $term,
                'costMicros' => $row->getMetrics()->getCostMicros(),
                'clicks' => $row->getMetrics()->getClicks(),
                'conversions' => $row->getMetrics()->getConversions(),
                'matchedPattern' => $matchedPattern,
                'suggestedMatchType' => $this->suggestMatchType($term, $matchedPattern),
            ];
        }

        // Sort by cost descending so the most wasteful terms surface first.
        usort($suggestions, fn (array $a, array $b) => $b['costMicros'] <=> $a['costMicros']);

        return [
            'campaignId' => $arguments['campaignId'],
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Returns the first matching bad-traffic pattern, or null if the term is clean.
     */
    private function matchesBadTraffic(string $term): ?string
    {
        $termLower = mb_strtolower($term);

        foreach (self::BAD_TRAFFIC_PATTERNS as $pattern) {
            if (str_contains($termLower, $pattern)) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Suggest EXACT for short exact-match candidates, PHRASE for multi-word patterns.
     */
    private function suggestMatchType(string $term, string $pattern): string
    {
        return str_contains($pattern, ' ') ? 'PHRASE' : 'EXACT';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaignId' => $schema->string()->required(),
            'minCostMicros' => $schema->integer(),
            'limit' => $schema->integer(),
        ];
    }
}
