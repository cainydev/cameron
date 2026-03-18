<?php

declare(strict_types=1);

use App\Models\Shop;
use App\Services\ShopwareApiService;

it('throws when shopware_url is missing', function () {
    $shop = Shop::factory()->make(['shopware_url' => null, 'shopware_client_id' => 'id', 'shopware_client_secret' => 'secret']);

    expect(fn () => new ShopwareApiService($shop))
        ->toThrow(RuntimeException::class, 'Shopware URL');
});

it('throws when shopware_client_id is missing', function () {
    $shop = Shop::factory()->make(['shopware_url' => 'https://shop.example.com', 'shopware_client_id' => null, 'shopware_client_secret' => 'secret']);

    expect(fn () => new ShopwareApiService($shop))
        ->toThrow(RuntimeException::class, 'client ID');
});

it('throws when shopware_client_secret is missing', function () {
    $shop = Shop::factory()->make(['shopware_url' => 'https://shop.example.com', 'shopware_client_id' => 'id', 'shopware_client_secret' => null]);

    expect(fn () => new ShopwareApiService($shop))
        ->toThrow(RuntimeException::class, 'client secret');
});

it('instantiates successfully with full credentials', function () {
    $shop = Shop::factory()->make([
        'shopware_url' => 'https://shop.example.com',
        'shopware_client_id' => 'my-client-id',
        'shopware_client_secret' => 'my-client-secret',
    ]);

    expect(new ShopwareApiService($shop))->toBeInstanceOf(ShopwareApiService::class);
});
