<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Google\Ads\GoogleAds\V20\Resources\AdGroupCriterion;
use Google\Ads\GoogleAds\V20\Services\AdGroupCriterionOperation;
use Google\Ads\GoogleAds\V20\Services\MutateAdGroupCriteriaRequest;
use Google\Protobuf\FieldMask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Updates the CPC bid for a specific keyword (ad group criterion).
 *
 * Requires human approval before execution.
 */
class UpdateKeywordBid extends AbstractAgentTool
{
    protected bool $requiresApproval = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Update the CPC bid for a specific keyword (ad group criterion). Requires human approval before execution.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{adGroupId: string, criterionId: string, newCpcBidMicros: int, reason: string}  $arguments
     * @return array{success: bool, criterionId: string, newCpcBidMicros: int}
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $service = $this->googleApiService();
        $adsClient = $service->makeAdsClient();

        $criterion = new AdGroupCriterion([
            'resource_name' => sprintf(
                'customers/%s/adGroupCriteria/%s~%s',
                $customerId,
                $arguments['adGroupId'],
                $arguments['criterionId'],
            ),
            'cpc_bid_micros' => $arguments['newCpcBidMicros'],
        ]);

        $operation = new AdGroupCriterionOperation;
        $operation->setUpdate($criterion);
        $operation->setUpdateMask(new FieldMask(['paths' => ['cpc_bid_micros']]));

        $request = new MutateAdGroupCriteriaRequest([
            'customer_id' => $customerId,
            'operations' => [$operation],
        ]);

        $adsClient->getAdGroupCriterionServiceClient()->mutateAdGroupCriteria($request);

        return [
            'success' => true,
            'criterionId' => $arguments['criterionId'],
            'newCpcBidMicros' => $arguments['newCpcBidMicros'],
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'adGroupId' => $schema->string()->required(),
            'criterionId' => $schema->string()->required(),
            'newCpcBidMicros' => $schema->integer()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
