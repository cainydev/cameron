<?php

declare(strict_types=1);

use App\Ai\ToolRegistry;
use App\Ai\Tools\Shopware\GetShopwareCategories;
use App\Ai\Tools\Shopware\GetShopwareProducts;
use App\Ai\Tools\Shopware\UpdateProductSeoContent;
use App\Enums\ToolCategory;
use App\Models\Shop;

beforeEach(function () {
    ToolRegistry::clearCache();
});

it('discovers Shopware tools in subdirectory', function () {
    $classes = ToolRegistry::discoverToolClasses();

    expect($classes)
        ->toContain(GetShopwareCategories::class)
        ->toContain(GetShopwareProducts::class)
        ->toContain(UpdateProductSeoContent::class);
});

it('includes Shopware tools when shop has shopware_url', function () {
    $shop = Shop::factory()->create([
        'shopware_url' => 'https://shop.example.com',
        'shopware_client_id' => 'my-client-id',
        'shopware_client_secret' => 'my-secret',
    ]);

    $tools = (new ToolRegistry)->forShop($shop)->resolve();
    $classes = array_map(fn ($t) => get_class($t), $tools);

    expect($classes)->toContain(GetShopwareCategories::class);
});

it('excludes Shopware tools when shop has no shopware_url', function () {
    $shop = Shop::factory()->create(['shopware_url' => null]);

    $tools = (new ToolRegistry)->forShop($shop)->resolve();
    $classes = array_map(fn ($t) => get_class($t), $tools);

    expect($classes)->not->toContain(GetShopwareCategories::class);
});

it('Shopware ToolCategory has correct required shop field', function () {
    expect(ToolCategory::Shopware->requiredShopField())->toBe('shopware_url');
});

it('Shopware ToolCategory is user configurable', function () {
    expect(ToolCategory::Shopware->isUserConfigurable())->toBeTrue();
});
