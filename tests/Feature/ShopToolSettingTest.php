<?php

use App\Enums\ToolCategory;
use App\Models\Shop;
use App\Models\ShopToolSetting;
use Illuminate\Database\UniqueConstraintViolationException;

it('can be created with factory defaults', function () {
    $setting = ShopToolSetting::factory()->create();

    expect($setting)->toBeInstanceOf(ShopToolSetting::class)
        ->and($setting->is_enabled)->toBeTrue()
        ->and($setting->approval_mode)->toBe('default');
});

it('casts category to ToolCategory enum', function () {
    $setting = ShopToolSetting::factory()->create([
        'category' => ToolCategory::GoogleAds,
    ]);

    expect($setting->fresh()->category)->toBe(ToolCategory::GoogleAds);
});

it('casts tool_overrides to array', function () {
    $overrides = ['UpdateAdsCampaignStatus' => ['requires_approval' => false]];

    $setting = ShopToolSetting::factory()->create([
        'tool_overrides' => $overrides,
    ]);

    expect($setting->fresh()->tool_overrides)->toBe($overrides);
});

it('belongs to a shop', function () {
    $shop = Shop::factory()->create();
    $setting = ShopToolSetting::factory()->for($shop)->create();

    expect($setting->shop->id)->toBe($shop->id);
});

it('enforces unique shop_id + category constraint', function () {
    $shop = Shop::factory()->create();

    ShopToolSetting::factory()->for($shop)->create(['category' => ToolCategory::GoogleAds]);

    expect(fn () => ShopToolSetting::factory()->for($shop)->create(['category' => ToolCategory::GoogleAds]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('has a disabled factory state', function () {
    $setting = ShopToolSetting::factory()->disabled()->create();

    expect($setting->is_enabled)->toBeFalse();
});

it('has an autoApprove factory state', function () {
    $setting = ShopToolSetting::factory()->autoApprove()->create();

    expect($setting->approval_mode)->toBe('auto');
});

it('shop has toolSettings relationship', function () {
    $shop = Shop::factory()->create();

    ShopToolSetting::factory()->for($shop)->create(['category' => ToolCategory::GoogleAds]);
    ShopToolSetting::factory()->for($shop)->create(['category' => ToolCategory::Memory]);

    expect($shop->toolSettings)->toHaveCount(2);
});
