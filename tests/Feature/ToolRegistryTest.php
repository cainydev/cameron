<?php

use App\Ai\ToolRegistry;
use App\Ai\Tools\AbstractAgentTool;
use App\Ai\Tools\AddNegativeKeyword;
use App\Ai\Tools\GetGa4Traffic;
use App\Ai\Tools\GetGoogleAdsCampaigns;
use App\Ai\Tools\MarkTaskAsResolved;
use App\Ai\Tools\ReadAdsKnowledge;
use App\Ai\Tools\UpdateAdsCampaignStatus;
use App\Enums\ToolCategory;
use App\Models\Shop;
use App\Models\ShopToolSetting;

beforeEach(function () {
    ToolRegistry::clearCache();
});

it('discovers tool classes from the Tools directory', function () {
    $classes = ToolRegistry::discoverToolClasses();

    expect($classes)->not->toBeEmpty()
        ->and($classes)->each->toBeString();
});

it('does not include AbstractAgentTool in discovered classes', function () {
    $classes = ToolRegistry::discoverToolClasses();

    expect($classes)->not->toContain(AbstractAgentTool::class);
});

it('resolves all tools for a fully configured shop', function () {
    $shop = Shop::factory()->make();

    $tools = app(ToolRegistry::class)
        ->forShop($shop)
        ->resolve();

    expect($tools)->not->toBeEmpty();
    expect($tools)->each->toBeInstanceOf(AbstractAgentTool::class);
});

it('filters by inCategories', function () {
    $shop = Shop::factory()->make();

    $tools = app(ToolRegistry::class)
        ->forShop($shop)
        ->inCategories([ToolCategory::System])
        ->resolve();

    $classes = array_map(fn ($t) => $t::class, $tools);

    expect($classes)->toContain(MarkTaskAsResolved::class);
    expect($classes)->not->toContain(GetGa4Traffic::class);
});

it('filters by excludeCategories', function () {
    $shop = Shop::factory()->make();

    $tools = app(ToolRegistry::class)
        ->forShop($shop)
        ->excludeCategories([ToolCategory::System])
        ->resolve();

    $classes = array_map(fn ($t) => $t::class, $tools);

    expect($classes)->not->toContain(MarkTaskAsResolved::class);
});

it('excludes tools when shop is missing required field', function () {
    $shop = Shop::factory()->make(['ga4_property_id' => null]);

    $tools = app(ToolRegistry::class)
        ->forShop($shop)
        ->resolve();

    $classes = array_map(fn ($t) => $t::class, $tools);

    expect($classes)->not->toContain(GetGa4Traffic::class);
    expect($classes)->toContain(GetGoogleAdsCampaigns::class);
});

it('includes ReadAdsKnowledge even without google_ads_customer_id', function () {
    $shop = Shop::factory()->make(['google_ads_customer_id' => null]);

    $tools = app(ToolRegistry::class)
        ->forShop($shop)
        ->resolve();

    $classes = array_map(fn ($t) => $t::class, $tools);

    expect($classes)->toContain(ReadAdsKnowledge::class);
});

it('excludes approval-required tools when excludeApprovalRequired is called', function () {
    $shop = Shop::factory()->make();

    $tools = app(ToolRegistry::class)
        ->forShop($shop)
        ->excludeApprovalRequired()
        ->resolve();

    $classes = array_map(fn ($t) => $t::class, $tools);

    expect($classes)->not->toContain(UpdateAdsCampaignStatus::class);
    expect($classes)->not->toContain(AddNegativeKeyword::class);
});

it('sets shop context on resolved tools', function () {
    $shop = Shop::factory()->make();

    $tools = app(ToolRegistry::class)
        ->forShop($shop)
        ->inCategories([ToolCategory::GoogleAnalytics])
        ->resolve();

    $ref = new ReflectionClass($tools[0]);
    $prop = $ref->getProperty('shop');

    expect($prop->getValue($tools[0]))->toBe($shop);
});

it('sets task context on resolved tools', function () {
    $shop = Shop::factory()->make();

    $tools = app(ToolRegistry::class)
        ->forShop($shop)
        ->forTask(42)
        ->inCategories([ToolCategory::System])
        ->resolve();

    $ref = new ReflectionClass($tools[0]);
    $prop = $ref->getProperty('activeTaskId');

    expect($prop->getValue($tools[0]))->toBe(42);
});

it('respects disabled category from shop tool settings', function () {
    $shop = Shop::factory()->create();

    ShopToolSetting::factory()->for($shop)->create([
        'category' => ToolCategory::GoogleAnalytics,
        'is_enabled' => false,
    ]);

    $shop->load('toolSettings');

    $tools = app(ToolRegistry::class)
        ->forShop($shop)
        ->resolve();

    $classes = array_map(fn ($t) => $t::class, $tools);

    expect($classes)->not->toContain(GetGa4Traffic::class);
});

it('overrides approval mode to auto from shop tool settings', function () {
    $shop = Shop::factory()->create();

    ShopToolSetting::factory()->for($shop)->create([
        'category' => ToolCategory::GoogleAds,
        'approval_mode' => 'auto',
    ]);

    $shop->load('toolSettings');

    $tools = app(ToolRegistry::class)
        ->forShop($shop)
        ->inCategories([ToolCategory::GoogleAds])
        ->resolve();

    $updateTool = collect($tools)->first(fn ($t) => $t instanceof UpdateAdsCampaignStatus);

    expect($updateTool)->not->toBeNull();

    $ref = new ReflectionClass($updateTool);
    $prop = $ref->getProperty('requiresApproval');

    expect($prop->getValue($updateTool))->toBeFalse();
});

it('is immutable and returns new instances on each builder call', function () {
    $registry = app(ToolRegistry::class);
    $withShop = $registry->forShop(Shop::factory()->make());

    expect($withShop)->not->toBe($registry);
});
