<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Lists products from Google Merchant Center with their status and attributes.
 */
#[Category(ToolCategory::GoogleAds)]
class GetMerchantProducts extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function requiredShopFields(): array
    {
        return ['merchant_center_id'];
    }

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Merchant Center Products';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List products from Google Merchant Center including title, brand, availability, price, and channel. Use to audit feed quality.';
    }

    /**
     * @param  array{pageSize?: int, pageToken?: string}  $arguments
     * @return array{merchantId: string, products: array<int, array{id: string, offerId: string, title: string, brand: string|null, availability: string|null, price: string|null, channel: string}>}
     */
    public function execute(array $arguments): array
    {
        $merchantId = $this->shop?->merchant_center_id
            ?? throw new \RuntimeException('Shop has no Merchant Center ID configured.');

        $params = ['pageSize' => $arguments['pageSize'] ?? 50];

        if (! empty($arguments['pageToken'])) {
            $params['pageToken'] = $arguments['pageToken'];
        }

        $client = $this->googleApiService()->makeMerchantApiClient();
        $response = $client->get("products/v1beta/accounts/{$merchantId}/products", $params);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("Merchant API error {$response->status()}: {$error}");
        }

        $products = [];

        foreach ($response->json('products', []) as $product) {
            $attrs = $product['attributes'] ?? [];
            $price = $attrs['price'] ?? null;

            $products[] = [
                'id' => $product['name'] ?? '',
                'offerId' => $product['offerId'] ?? '',
                'title' => $attrs['title'] ?? '',
                'brand' => $attrs['brand'] ?? null,
                'availability' => $attrs['availability'] ?? null,
                'price' => $price ? ($price['amountMicros'] / 1_000_000).' '.$price['currencyCode'] : null,
                'channel' => $product['channel'] ?? '',
            ];
        }

        return [
            'merchantId' => $merchantId,
            'products' => $products,
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'pageSize' => $schema->integer(),
            'pageToken' => $schema->string(),
        ];
    }
}
