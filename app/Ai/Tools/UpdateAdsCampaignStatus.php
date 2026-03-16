<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Enums\CampaignStatusEnum\CampaignStatus;
use Google\Ads\GoogleAds\V20\Resources\Campaign;
use Google\Ads\GoogleAds\V20\Services\CampaignOperation;
use Google\Ads\GoogleAds\V20\Services\MutateCampaignsRequest;
use Google\Protobuf\FieldMask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Updates a Google Ads campaign's status (ENABLED or PAUSED).
 *
 * Requires human approval before execution.
 */
#[Category(ToolCategory::GoogleAds)]
class UpdateAdsCampaignStatus extends AbstractAgentTool
{
    protected bool $requiresApproval = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        if (! empty($arguments['campaignId']) && ! empty($arguments['status'])) {
            return "Set Campaign #{$arguments['campaignId']} → {$arguments['status']}";
        }

        return 'Update Campaign Status';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Update a Google Ads campaign status to ENABLED or PAUSED. Requires human approval before execution.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{campaignId: string, status: string, reason: string}  $arguments
     * @return array{success: bool, campaignId: string, status: string}
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $service = $this->googleApiService();
        $adsClient = $service->makeAdsClient();

        $statusValue = $arguments['status'] === 'ENABLED' ? CampaignStatus::ENABLED : CampaignStatus::PAUSED;

        $campaign = new Campaign([
            'resource_name' => sprintf('customers/%s/campaigns/%s', $customerId, $arguments['campaignId']),
            'status' => $statusValue,
        ]);

        $operation = new CampaignOperation;
        $operation->setUpdate($campaign);
        $operation->setUpdateMask(new FieldMask(['paths' => ['status']]));

        $request = new MutateCampaignsRequest([
            'customer_id' => $customerId,
            'operations' => [$operation],
        ]);

        $adsClient->getCampaignServiceClient()->mutateCampaigns($request);

        return [
            'success' => true,
            'campaignId' => $arguments['campaignId'],
            'status' => $arguments['status'],
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaignId' => $schema->string()->required(),
            'status' => $schema->string()->enum(['ENABLED', 'PAUSED'])->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
