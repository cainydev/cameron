<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Common\KeywordInfo;
use Google\Ads\GoogleAds\V20\Enums\KeywordMatchTypeEnum\KeywordMatchType;
use Google\Ads\GoogleAds\V20\Resources\CampaignCriterion;
use Google\Ads\GoogleAds\V20\Services\CampaignCriterionOperation;
use Google\Ads\GoogleAds\V20\Services\MutateCampaignCriteriaRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Adds a negative keyword to a Google Ads campaign.
 *
 * Requires human approval before execution.
 */
#[Category(ToolCategory::GoogleAds)]
class AddNegativeKeyword extends AbstractAgentTool
{
    protected bool $requiresApproval = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        if (! empty($arguments['keyword'])) {
            return "Add Negative: \"{$arguments['keyword']}\"";
        }

        return 'Add Negative Keyword';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Add a campaign-level negative keyword to block irrelevant search terms. Requires human approval.';
    }

    /**
     * @param  array{campaignId: string, keyword: string, matchType: string, reason: string}  $arguments
     * @return array{success: bool, campaignId: string, keyword: string, matchType: string}
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $matchTypeValue = match ($arguments['matchType']) {
            'EXACT' => KeywordMatchType::EXACT,
            'PHRASE' => KeywordMatchType::PHRASE,
            'BROAD' => KeywordMatchType::BROAD,
            default => throw new \RuntimeException("Invalid match type: {$arguments['matchType']}"),
        };

        $criterion = new CampaignCriterion([
            'campaign' => sprintf('customers/%s/campaigns/%s', $customerId, $arguments['campaignId']),
            'negative' => true,
            'keyword' => new KeywordInfo([
                'text' => $arguments['keyword'],
                'match_type' => $matchTypeValue,
            ]),
        ]);

        $operation = new CampaignCriterionOperation;
        $operation->setCreate($criterion);

        $request = new MutateCampaignCriteriaRequest([
            'customer_id' => $customerId,
            'operations' => [$operation],
        ]);

        $this->googleApiService()->makeAdsClient()
            ->getCampaignCriterionServiceClient()->mutateCampaignCriteria($request);

        return [
            'success' => true,
            'campaignId' => $arguments['campaignId'],
            'keyword' => $arguments['keyword'],
            'matchType' => $arguments['matchType'],
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaignId' => $schema->string()->required(),
            'keyword' => $schema->string()->required(),
            'matchType' => $schema->string()->enum(['EXACT', 'PHRASE', 'BROAD'])->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
