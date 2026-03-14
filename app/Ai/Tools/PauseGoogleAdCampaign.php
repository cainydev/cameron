<?php

declare(strict_types=1);

namespace App\Ai\Tools;

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
     * @param  array{campaign_id: int, reason: string}  $arguments
     * @return array{success: bool, campaign_id: int, status: string}
     */
    public function execute(array $arguments): array
    {
        // TODO: Integrate with the Google Ads API to actually pause the campaign.
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
            'campaign_id' => $schema->integer()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
