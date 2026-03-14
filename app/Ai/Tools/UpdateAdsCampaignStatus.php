<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Services\GoogleApiService;
use Google\Ads\GoogleAds\V20\Enums\CampaignStatusEnum\CampaignStatus;
use Google\Ads\GoogleAds\V20\Resources\Campaign;
use Google\Ads\GoogleAds\V20\Services\CampaignOperation;
use Google\Ads\GoogleAds\V20\Services\MutateCampaignsRequest;
use Google\Protobuf\FieldMask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Stringable;

/**
 * Updates a Google Ads campaign's status (ENABLED or PAUSED).
 *
 * Requires human approval before execution.
 */
class UpdateAdsCampaignStatus extends AbstractAgentTool
{
    protected bool $requiresApproval = true;

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
     * @param  array{customerId: string, campaignId: string, status: string, reason: string}  $arguments
     * @return array{success: bool, campaignId: string, status: string}
     */
    public function execute(array $arguments): array
    {
        $service = new GoogleApiService(Auth::user());
        $adsClient = $service->makeAdsClient();

        $statusValue = $arguments['status'] === 'ENABLED' ? CampaignStatus::ENABLED : CampaignStatus::PAUSED;

        $campaign = new Campaign([
            'resource_name' => sprintf('customers/%s/campaigns/%s', $arguments['customerId'], $arguments['campaignId']),
            'status' => $statusValue,
        ]);

        $operation = new CampaignOperation;
        $operation->setUpdate($campaign);
        $operation->setUpdateMask(new FieldMask(['paths' => ['status']]));

        $request = new MutateCampaignsRequest([
            'customer_id' => $arguments['customerId'],
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
            'customerId' => $schema->string()->required(),
            'campaignId' => $schema->string()->required(),
            'status' => $schema->string()->enum(['ENABLED', 'PAUSED'])->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
