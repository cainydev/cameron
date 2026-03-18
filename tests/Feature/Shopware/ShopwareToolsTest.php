<?php

declare(strict_types=1);

use App\Ai\Tools\Shopware\GetShopwareCategories;
use App\Ai\Tools\Shopware\GetShopwareCurrencies;
use App\Ai\Tools\Shopware\GetShopwareCustomFieldSets;
use App\Ai\Tools\Shopware\GetShopwareLanguages;
use App\Ai\Tools\Shopware\GetShopwareProduct;
use App\Ai\Tools\Shopware\GetShopwareProducts;
use App\Ai\Tools\Shopware\GetShopwareProperties;
use App\Ai\Tools\Shopware\GetShopwareSalesChannels;
use App\Ai\Tools\Shopware\GetShopwareTags;
use App\Ai\Tools\Shopware\UpdateCategorySeoContent;
use App\Ai\Tools\Shopware\UpdateProductCrossSellings;
use App\Ai\Tools\Shopware\UpdateProductCustomFields;
use App\Ai\Tools\Shopware\UpdateProductSeoContent;
use App\Ai\Tools\Shopware\UpdateProductTags;
use App\Enums\ToolCategory;
use App\Models\Shop;

beforeEach(function () {
    $this->shop = Shop::factory()->make([
        'id' => 1,
        'shopware_url' => 'https://shop.example.com',
        'shopware_client_id' => 'my-client-id',
        'shopware_client_secret' => 'my-client-secret',
    ]);
});

it('all read tools are marked as read-only', function (string $class) {
    $tool = (new $class)->forShop($this->shop);
    $ref = new ReflectionProperty($tool, 'isReadOnly');
    $ref->setAccessible(true);

    expect($ref->getValue($tool))->toBeTrue();
})->with([
    GetShopwareCategories::class,
    GetShopwareProducts::class,
    GetShopwareProduct::class,
    GetShopwareCustomFieldSets::class,
    GetShopwareTags::class,
    GetShopwareSalesChannels::class,
    GetShopwareLanguages::class,
    GetShopwareCurrencies::class,
    GetShopwareProperties::class,
]);

it('all write tools are not read-only', function (string $class) {
    $tool = (new $class)->forShop($this->shop);
    $ref = new ReflectionProperty($tool, 'isReadOnly');
    $ref->setAccessible(true);

    expect($ref->getValue($tool))->toBeFalse();
})->with([
    UpdateProductSeoContent::class,
    UpdateProductTags::class,
    UpdateProductCustomFields::class,
    UpdateProductCrossSellings::class,
    UpdateCategorySeoContent::class,
]);

it('all tools belong to Shopware category', function (string $class) {
    $tool = new $class;

    expect($tool->category())->toBe(ToolCategory::Shopware);
})->with([
    GetShopwareCategories::class,
    GetShopwareProducts::class,
    GetShopwareProduct::class,
    GetShopwareCustomFieldSets::class,
    GetShopwareTags::class,
    GetShopwareSalesChannels::class,
    GetShopwareLanguages::class,
    GetShopwareCurrencies::class,
    GetShopwareProperties::class,
    UpdateProductSeoContent::class,
    UpdateProductTags::class,
    UpdateProductCustomFields::class,
    UpdateProductCrossSellings::class,
    UpdateCategorySeoContent::class,
]);

it('UpdateProductSeoContent returns early when no fields given', function () {
    $tool = (new UpdateProductSeoContent)->forShop($this->shop);

    $result = $tool->execute(['product_id' => 'abc-123']);

    expect($result)->toBe('No fields to update were provided.');
});

it('UpdateCategorySeoContent returns early when no fields given', function () {
    $tool = (new UpdateCategorySeoContent)->forShop($this->shop);

    $result = $tool->execute(['category_id' => 'abc-123']);

    expect($result)->toBe('No fields to update were provided.');
});

it('UpdateProductCustomFields throws when custom_fields is empty', function () {
    $tool = (new UpdateProductCustomFields)->forShop($this->shop);

    expect(fn () => $tool->execute(['product_id' => 'abc-123', 'custom_fields' => []]))
        ->toThrow(RuntimeException::class, 'non-empty');
});

it('UpdateProductSeoContent throws when product_id missing', function () {
    $tool = (new UpdateProductSeoContent)->forShop($this->shop);

    expect(fn () => $tool->execute(['name' => 'New Name']))
        ->toThrow(RuntimeException::class, 'product_id');
});

it('tool throws without shop context', function (string $class) {
    $tool = new $class;

    expect(fn () => $tool->execute([]))
        ->toThrow(RuntimeException::class);
})->with([
    GetShopwareCategories::class,
    GetShopwareProducts::class,
]);
