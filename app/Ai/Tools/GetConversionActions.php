<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Ai\Concerns\FormatsToolOutput;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Enums\ConversionActionCategoryEnum\ConversionActionCategory;
use Google\Ads\GoogleAds\V20\Enums\ConversionActionCountingTypeEnum\ConversionActionCountingType;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves all enabled conversion actions from Google Ads with their configuration.
 */
#[Category(ToolCategory::GoogleAds)]
class GetConversionActions extends AbstractAgentTool
{
    use FormatsToolOutput;

    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Conversion Actions';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List all enabled Google Ads conversion actions with their category (purchase, lead, etc.), counting type (one-per-click vs every), whether they are primary goals, default value, and lookback window. Call this to understand what the account is actually optimising for before making any bidding or strategy recommendations.';
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, array{id: int, name: string, category: string, primary: bool, countingType: string, defaultValue: float, lookbackDays: int}>
     */
    public function execute(array $arguments): array
    {
        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $query = 'SELECT conversion_action.id, conversion_action.name, conversion_action.status,
                         conversion_action.category, conversion_action.counting_type,
                         conversion_action.value_settings.default_value,
                         conversion_action.value_settings.always_use_default_value,
                         conversion_action.primary_for_goal,
                         conversion_action.click_through_lookback_window_days
                  FROM conversion_action
                  WHERE conversion_action.status = ENABLED
                  ORDER BY conversion_action.primary_for_goal DESC, conversion_action.name ASC
                  LIMIT 50';

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $response = $this->googleApiService()->makeAdsClient()
            ->getGoogleAdsServiceClient()->search($request);

        $actions = [];

        foreach ($response->iterateAllElements() as $row) {
            $ca = $row->getConversionAction();
            $valueSettings = $ca->getValueSettings();

            $actions[] = [
                'id' => $ca->getId(),
                'name' => $ca->getName(),
                'category' => $this->conversionCategoryLabel($ca->getCategory()),
                'primary' => $ca->getPrimaryForGoal(),
                'countingType' => $this->countingTypeLabel($ca->getCountingType()),
                'defaultValue' => $valueSettings ? round($valueSettings->getDefaultValue(), 2) : 0.0,
                'alwaysUseDefaultValue' => $valueSettings ? $valueSettings->getAlwaysUseDefaultValue() : false,
                'lookbackDays' => $ca->getClickThroughLookbackWindowDays(),
            ];
        }

        return $actions;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    private function conversionCategoryLabel(int $category): string
    {
        return match ($category) {
            ConversionActionCategory::PURCHASE => 'PURCHASE',
            ConversionActionCategory::SIGNUP => 'SIGNUP',
            ConversionActionCategory::PAGE_VIEW => 'PAGE_VIEW',
            ConversionActionCategory::CONTACT => 'CONTACT',
            ConversionActionCategory::DOWNLOAD => 'DOWNLOAD',
            ConversionActionCategory::ENGAGEMENT => 'ENGAGEMENT',
            ConversionActionCategory::ADD_TO_CART => 'ADD_TO_CART',
            ConversionActionCategory::BEGIN_CHECKOUT => 'BEGIN_CHECKOUT',
            ConversionActionCategory::SUBSCRIBE_PAID => 'SUBSCRIBE_PAID',
            ConversionActionCategory::PHONE_CALL_LEAD => 'PHONE_CALL_LEAD',
            ConversionActionCategory::IMPORTED_LEAD => 'IMPORTED_LEAD',
            ConversionActionCategory::SUBMIT_LEAD_FORM => 'SUBMIT_LEAD_FORM',
            ConversionActionCategory::QUALIFIED_LEAD => 'QUALIFIED_LEAD',
            ConversionActionCategory::CONVERTED_LEAD => 'CONVERTED_LEAD',
            ConversionActionCategory::GET_DIRECTIONS => 'GET_DIRECTIONS',
            ConversionActionCategory::OUTBOUND_CLICK => 'OUTBOUND_CLICK',
            ConversionActionCategory::STORE_VISIT => 'STORE_VISIT',
            ConversionActionCategory::STORE_SALE => 'STORE_SALE',
            default => 'OTHER',
        };
    }

    private function countingTypeLabel(int $type): string
    {
        return match ($type) {
            ConversionActionCountingType::ONE_PER_CLICK => 'ONE_PER_CLICK',
            ConversionActionCountingType::MANY_PER_CLICK => 'MANY_PER_CLICK',
            default => 'UNKNOWN',
        };
    }
}
