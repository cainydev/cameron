<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Ai\Concerns\FormatsToolOutput;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Enums\AdvertisingChannelTypeEnum\AdvertisingChannelType;
use Google\Ads\GoogleAds\V20\Enums\BiddingStrategyTypeEnum\BiddingStrategyType;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves a list of Google Ads campaigns for the shop's customer ID.
 */
#[Category(ToolCategory::GoogleAds)]
class GetGoogleAdsCampaigns extends AbstractAgentTool
{
    use FormatsToolOutput;

    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Google Ads Campaigns';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List Google Ads campaigns with full strategy context: type (Search/PMax/Shopping), bidding strategy, daily budget, target ROAS/CPA, spend, impressions, clicks, conversions, impression share, and budget/rank lost impression share. Excludes removed campaigns. Always call this before making any budget or bidding recommendations.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{limit?: int}  $arguments
     * @return array<int, array{id: int, name: string, status: string, type: string, biddingStrategy: string, dailyBudget: float, targetRoas: float|null, targetCpaMicros: float|null, cost: float, impressions: int, clicks: int, conversions: float, impressionShare: string, budgetLostIS: string, rankLostIS: string}>
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $limit = $arguments['limit'] ?? 100;

        $query = "SELECT campaign.id, campaign.name, campaign.status,
                         campaign.advertising_channel_type, campaign.bidding_strategy_type,
                         campaign.target_roas.target_roas, campaign.target_cpa.target_cpa_micros,
                         campaign.maximize_conversion_value.target_roas,
                         campaign.maximize_conversions.target_cpa_micros,
                         campaign_budget.amount_micros,
                         metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions,
                         metrics.search_impression_share,
                         metrics.search_budget_lost_impression_share,
                         metrics.search_rank_lost_impression_share
                  FROM campaign
                  WHERE segments.date DURING LAST_30_DAYS
                    AND campaign.status IN ('ENABLED', 'PAUSED')
                  ORDER BY metrics.cost_micros DESC
                  LIMIT {$limit}";

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $response = $this->googleApiService()->makeAdsClient()
            ->getGoogleAdsServiceClient()->search($request);

        $campaigns = [];

        foreach ($response->iterateAllElements() as $row) {
            $c = $row->getCampaign();
            $m = $row->getMetrics();

            // Resolve target ROAS — can live on different bidding strategy objects
            $targetRoas = $c->getTargetRoas()?->getTargetRoas()
                ?? $c->getMaximizeConversionValue()?->getTargetRoas()
                ?: null;

            // Resolve target CPA in currency units
            $targetCpaMicros = $c->getTargetCpa()?->getTargetCpaMicros()
                ?? $c->getMaximizeConversions()?->getTargetCpaMicros()
                ?: null;

            $campaigns[] = [
                'id' => $c->getId(),
                'name' => $c->getName(),
                'status' => $this->campaignStatusLabel($c->getStatus()),
                'type' => $this->channelTypeLabel($c->getAdvertisingChannelType()),
                'biddingStrategy' => $this->biddingStrategyLabel($c->getBiddingStrategyType()),
                'dailyBudget' => $this->microsToCurrency($row->getCampaignBudget()->getAmountMicros()),
                'targetRoas' => $targetRoas ? round($targetRoas, 2) : null,
                'targetCpa' => $targetCpaMicros ? $this->microsToCurrency($targetCpaMicros) : null,
                'cost' => $this->microsToCurrency($m->getCostMicros()),
                'impressions' => (int) $m->getImpressions(),
                'clicks' => (int) $m->getClicks(),
                'conversions' => round($m->getConversions(), 2),
                'impressionShare' => $this->toPercent($m->getSearchImpressionShare()),
                'budgetLostIS' => $this->toPercent($m->getSearchBudgetLostImpressionShare()),
                'rankLostIS' => $this->toPercent($m->getSearchRankLostImpressionShare()),
            ];
        }

        return $campaigns;
    }

    private function channelTypeLabel(int $type): string
    {
        return match ($type) {
            AdvertisingChannelType::SEARCH => 'SEARCH',
            AdvertisingChannelType::DISPLAY => 'DISPLAY',
            AdvertisingChannelType::SHOPPING => 'SHOPPING',
            AdvertisingChannelType::VIDEO => 'VIDEO',
            AdvertisingChannelType::PERFORMANCE_MAX => 'PERFORMANCE_MAX',
            default => 'OTHER',
        };
    }

    private function biddingStrategyLabel(int $type): string
    {
        return match ($type) {
            BiddingStrategyType::TARGET_CPA => 'TARGET_CPA',
            BiddingStrategyType::TARGET_ROAS => 'TARGET_ROAS',
            BiddingStrategyType::MAXIMIZE_CONVERSIONS => 'MAXIMIZE_CONVERSIONS',
            BiddingStrategyType::MAXIMIZE_CONVERSION_VALUE => 'MAXIMIZE_CONVERSION_VALUE',
            BiddingStrategyType::MANUAL_CPC => 'MANUAL_CPC',
            BiddingStrategyType::TARGET_SPEND => 'MAXIMIZE_CLICKS',
            default => 'OTHER',
        };
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
