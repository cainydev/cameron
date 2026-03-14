<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Google\Ads\GoogleAds\V20\Enums\CampaignStatusEnum\CampaignStatus;
use Google\Ads\GoogleAds\V20\Resources\Campaign;
use Google\Ads\GoogleAds\V20\Services\CampaignOperation;
use Google\Ads\GoogleAds\V20\Services\MutateCampaignsRequest;
use Google\Protobuf\FieldMask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Pauses a Google Ads campaign.
 *
 * This is a destructive action that requires human approval before execution.
 * The agent must provide both a campaign_id and a reason for the pause.
 */
class PauseGoogleAdCampaign extends AbstractAgentTool
{
    protected bool $requiresApproval = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Pause a Google Ads campaign. Requires human approval before execution.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{campaign_id: string, reason: string}  $arguments
     * @return array{success: bool, campaign_id: string, status: string}
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $service = $this->googleApiService();
        $adsClient = $service->makeAdsClient();

        $campaign = new Campaign([
            'resource_name' => sprintf('customers/%s/campaigns/%s', $customerId, $arguments['campaign_id']),
            'status' => CampaignStatus::PAUSED,
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
            'campaign_id' => $arguments['campaign_id'],
            'status' => 'paused',
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->string()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
