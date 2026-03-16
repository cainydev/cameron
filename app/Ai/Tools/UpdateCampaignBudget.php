<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Resources\CampaignBudget;
use Google\Ads\GoogleAds\V20\Services\CampaignBudgetOperation;
use Google\Ads\GoogleAds\V20\Services\MutateCampaignBudgetsRequest;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Google\Protobuf\FieldMask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Updates a Google Ads campaign's daily budget.
 *
 * Requires human approval before execution.
 */
#[Category(ToolCategory::GoogleAds)]
class UpdateCampaignBudget extends AbstractAgentTool
{
    protected bool $requiresApproval = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        if (! empty($arguments['campaignId']) && ! empty($arguments['newDailyBudgetMicros'])) {
            $dollars = number_format($arguments['newDailyBudgetMicros'] / 1_000_000, 2);

            return "Set Campaign #{$arguments['campaignId']} Budget → \${$dollars}/day";
        }

        return 'Update Campaign Budget';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Update a campaign\'s daily budget amount (in micros, 1 dollar = 1,000,000 micros). Requires human approval.';
    }

    /**
     * @param  array{campaignId: string, newDailyBudgetMicros: int, reason: string}  $arguments
     * @return array{success: bool, campaignId: string, newDailyBudgetMicros: int}
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $adsClient = $this->googleApiService()->makeAdsClient();

        // First, fetch the campaign's budget resource name
        $query = "SELECT campaign.campaign_budget
                  FROM campaign
                  WHERE campaign.id = {$arguments['campaignId']}
                  LIMIT 1";

        $searchRequest = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $searchResponse = $adsClient->getGoogleAdsServiceClient()->search($searchRequest);

        $budgetResourceName = null;

        foreach ($searchResponse->iterateAllElements() as $row) {
            $budgetResourceName = $row->getCampaign()->getCampaignBudget();
        }

        if (! $budgetResourceName) {
            throw new \RuntimeException("Could not find budget for campaign {$arguments['campaignId']}.");
        }

        // Update the budget
        $budget = new CampaignBudget([
            'resource_name' => $budgetResourceName,
            'amount_micros' => $arguments['newDailyBudgetMicros'],
        ]);

        $operation = new CampaignBudgetOperation;
        $operation->setUpdate($budget);
        $operation->setUpdateMask(new FieldMask(['paths' => ['amount_micros']]));

        $mutateRequest = new MutateCampaignBudgetsRequest([
            'customer_id' => $customerId,
            'operations' => [$operation],
        ]);

        $adsClient->getCampaignBudgetServiceClient()->mutateCampaignBudgets($mutateRequest);

        return [
            'success' => true,
            'campaignId' => $arguments['campaignId'],
            'newDailyBudgetMicros' => $arguments['newDailyBudgetMicros'],
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaignId' => $schema->string()->required(),
            'newDailyBudgetMicros' => $schema->integer()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
