<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;
use Vin\ShopwareSdk\Data\Criteria;

/**
 * Fetches available Shopware currencies.
 */
#[Category(ToolCategory::Shopware)]
class GetShopwareCurrencies extends AbstractShopwareTool
{
    protected bool $isReadOnly = true;

    public function description(): Stringable|string
    {
        return 'Fetch all configured Shopware currencies (id, isoCode, name, symbol, factor). Useful for price-aware copy and understanding the shop\'s currency setup.';
    }

    public function label(array $arguments = []): string
    {
        return 'Shopware Currencies';
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, array{id: string, isoCode: string, name: string, symbol: string, factor: float}>
     */
    public function execute(array $arguments): array
    {
        $criteria = new Criteria;
        $criteria->setLimit(50);

        $result = $this->shopwareApi()->search('currency', $criteria);

        $currencies = [];

        foreach ($result->getElements() as $currency) {
            $currencies[] = [
                'id' => $currency->id,
                'isoCode' => $currency->isoCode,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'factor' => $currency->factor,
            ];
        }

        return $currencies;
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
