<?php

use App\Models\AgentGoal;
use App\Models\Shop;
use App\Models\User;

it('belongs to a user', function () {
    $shop = Shop::factory()->create();

    expect($shop->user)->toBeInstanceOf(User::class);
});

it('has many goals', function () {
    $shop = Shop::factory()->create();
    AgentGoal::factory()->create(['shop_id' => $shop->id]);

    expect($shop->goals)->toHaveCount(1);
});

it('is mass assignable with all fields', function () {
    $user = User::factory()->create();
    $shop = Shop::create([
        'user_id' => $user->id,
        'name' => 'My Store',
        'url' => 'https://mystore.com',
        'timezone' => 'America/New_York',
        'currency' => 'EUR',
        'ga4_property_id' => '111222333',
        'google_ads_customer_id' => '9998887776',
        'search_console_url' => 'https://mystore.com',
        'base_instructions' => 'Always be helpful.',
        'brand_guidelines' => 'Professional tone.',
        'target_roas' => '4.5',
    ]);

    expect($shop->name)->toBe('My Store')
        ->and($shop->url)->toBe('https://mystore.com')
        ->and($shop->timezone)->toBe('America/New_York')
        ->and($shop->currency)->toBe('EUR')
        ->and($shop->ga4_property_id)->toBe('111222333')
        ->and($shop->google_ads_customer_id)->toBe('9998887776')
        ->and($shop->search_console_url)->toBe('https://mystore.com')
        ->and($shop->base_instructions)->toBe('Always be helpful.')
        ->and($shop->brand_guidelines)->toBe('Professional tone.')
        ->and($shop->target_roas)->toBe('4.5');
});

it('user has many shops', function () {
    $user = User::factory()->create();
    Shop::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->shops)->toHaveCount(2);
});

it('goal belongs to a shop', function () {
    $shop = Shop::factory()->create();
    $goal = AgentGoal::factory()->create(['shop_id' => $shop->id]);

    expect($goal->shop->id)->toBe($shop->id);
});

it('goal shop_id is nullable', function () {
    $goal = AgentGoal::factory()->create(['shop_id' => null]);

    expect($goal->shop_id)->toBeNull();
});

it('deleting a shop nullifies goal shop_id', function () {
    $shop = Shop::factory()->create();
    $goal = AgentGoal::factory()->create(['shop_id' => $shop->id]);

    $shop->delete();

    expect($goal->fresh()->shop_id)->toBeNull();
});
