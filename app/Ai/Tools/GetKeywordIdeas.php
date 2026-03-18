<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Concerns\FormatsToolOutput;
use Google\Ads\GoogleAds\V20\Enums\KeywordPlanCompetitionLevelEnum\KeywordPlanCompetitionLevel;
use Google\Ads\GoogleAds\V20\Services\GenerateKeywordIdeasRequest;
use Google\Ads\GoogleAds\V20\Services\KeywordSeed;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Generates keyword ideas with search volume and competition data via the Google Ads Keyword Planner.
 *
 * NOTE: Disabled until Standard Access is granted on the Google Ads developer token.
 * Re-enable by restoring the #[Category(ToolCategory::GoogleAds)] attribute.
 * Apply at Google Ads → Tools → API Center.
 */
class GetKeywordIdeas extends AbstractAgentTool
{
    use FormatsToolOutput;

    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Keyword Ideas';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Generate keyword ideas from seed keywords using the Google Ads Keyword Planner. Returns monthly search volume, competition level, competition index (0–100), and estimated top-of-page CPC bids. Use this to discover new keywords to target in search campaigns.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{keywords: array<string>, limit?: int, languageId?: int, locationIds?: array<int>}  $arguments
     * @return array<int, array{keyword: string, avgMonthlySearches: int, competition: string, competitionIndex: int, lowTopOfPageBid: float|null, highTopOfPageBid: float|null}>
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $limit = min($arguments['limit'] ?? 100, 1000);

        $keywords = is_array($arguments['keywords'])
            ? $arguments['keywords']
            : array_map('trim', explode(',', (string) $arguments['keywords']));

        $seed = new KeywordSeed;
        $seed->setKeywords($keywords);

        $request = new GenerateKeywordIdeasRequest;
        $request->setCustomerId($customerId);
        $request->setKeywordSeed($seed);
        $request->setPageSize($limit);

        if (! empty($arguments['languageId'])) {
            $request->setLanguage("languageConstants/{$arguments['languageId']}");
        }

        if (! empty($arguments['locationIds'])) {
            $rawIds = is_array($arguments['locationIds'])
                ? $arguments['locationIds']
                : array_map('trim', explode(',', (string) $arguments['locationIds']));
            $geoTargets = array_map(
                fn ($id) => "geoTargetConstants/{$id}",
                $rawIds,
            );
            $request->setGeoTargetConstants($geoTargets);
        }

        $client = $this->googleApiService()->makeAdsClient();
        $response = $client->getKeywordPlanIdeaServiceClient()->generateKeywordIdeas($request);

        $results = [];

        foreach ($response->iterateAllElements() as $result) {
            $metrics = $result->getKeywordIdeaMetrics();

            $results[] = [
                'keyword' => $result->getText(),
                'avgMonthlySearches' => $metrics ? (int) $metrics->getAvgMonthlySearches() : 0,
                'competition' => $this->competitionLabel($metrics?->getCompetition() ?? 0),
                'competitionIndex' => $metrics ? (int) $metrics->getCompetitionIndex() : 0,
                'lowTopOfPageBid' => $metrics?->getLowTopOfPageBidMicros()
                    ? $this->microsToCurrency($metrics->getLowTopOfPageBidMicros())
                    : null,
                'highTopOfPageBid' => $metrics?->getHighTopOfPageBidMicros()
                    ? $this->microsToCurrency($metrics->getHighTopOfPageBidMicros())
                    : null,
            ];
        }

        usort($results, fn ($a, $b) => $b['avgMonthlySearches'] <=> $a['avgMonthlySearches']);

        return $results;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keywords' => $schema->string()->required()->description('Comma-separated seed keywords (e.g. "running shoes,trail sneakers").'),
            'limit' => $schema->integer(),
            'languageId' => $schema->integer()->description('Google language constant ID. English = 1000, Spanish = 1003, French = 1002, German = 1001.'),
            'locationIds' => $schema->string()->description('Comma-separated Google geo target constant IDs (e.g. "2840,2826"). USA = 2840, UK = 2826, Australia = 2036, Canada = 2124.'),
        ];
    }

    private function competitionLabel(int $level): string
    {
        return match ($level) {
            KeywordPlanCompetitionLevel::LOW => 'LOW',
            KeywordPlanCompetitionLevel::MEDIUM => 'MEDIUM',
            KeywordPlanCompetitionLevel::HIGH => 'HIGH',
            default => 'UNKNOWN',
        };
    }
}
