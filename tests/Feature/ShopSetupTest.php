<?php

use App\Models\Shop;
use App\Models\User;
use Livewire\Livewire;

// --- /setup (Google connect page) ---

it('setup page loads for user without google connected', function () {
    $user = User::factory()->create(['google_refresh_token' => null]);

    $this->actingAs($user)
        ->get(route('shop.setup'))
        ->assertOk();
});

it('setup page loads for user with google connected', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);

    $this->actingAs($user)
        ->get(route('shop.setup'))
        ->assertOk();
});

// --- /shop/edit (create or update) ---

it('shop edit page loads for user with no shop yet', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);

    $this->actingAs($user)
        ->get(route('shop.edit'))
        ->assertOk();
});

it('shop edit page shows create mode when no shop exists', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);

    Livewire::actingAs($user)
        ->test('pages::shop.edit')
        ->assertSet('shopId', null);
});

it('shop edit page loads and is pre-populated with existing shop data', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);
    Shop::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test Shop',
        'url' => 'https://test.com',
        'timezone' => 'America/Chicago',
        'currency' => 'EUR',
        'target_roas' => '5.0',
        'base_instructions' => 'Stay focused.',
        'brand_guidelines' => 'Bold voice.',
    ]);

    Livewire::actingAs($user)
        ->test('pages::shop.edit')
        ->assertSet('name', 'Test Shop')
        ->assertSet('url', 'https://test.com')
        ->assertSet('timezone', 'America/Chicago')
        ->assertSet('currency', 'EUR')
        ->assertSet('targetRoas', '5.0')
        ->assertSet('baseInstructions', 'Stay focused.')
        ->assertSet('brandGuidelines', 'Bold voice.');
});

it('shop edit validation requires name', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);

    Livewire::actingAs($user)
        ->test('pages::shop.edit')
        ->set('name', '')
        ->set('timezone', 'UTC')
        ->set('currency', 'USD')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('shop edit validation requires timezone', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);

    Livewire::actingAs($user)
        ->test('pages::shop.edit')
        ->set('name', 'My Store')
        ->set('timezone', '')
        ->set('currency', 'USD')
        ->call('save')
        ->assertHasErrors(['timezone' => 'required']);
});

it('shop edit validation requires currency', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);

    Livewire::actingAs($user)
        ->test('pages::shop.edit')
        ->set('name', 'My Store')
        ->set('timezone', 'UTC')
        ->set('currency', '')
        ->call('save')
        ->assertHasErrors(['currency' => 'required']);
});

it('creates shop with correct data and redirects to cameron on first save', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);

    Livewire::actingAs($user)
        ->test('pages::shop.edit')
        ->set('name', 'My Store')
        ->set('url', 'https://mystore.com')
        ->set('timezone', 'America/New_York')
        ->set('currency', 'USD')
        ->set('targetRoas', '4.0')
        ->set('baseInstructions', 'Be helpful.')
        ->set('brandGuidelines', 'Professional tone.')
        ->call('save')
        ->assertRedirect(route('cameron'));

    $shop = Shop::where('user_id', $user->id)->first();

    expect($shop)->not->toBeNull()
        ->and($shop->name)->toBe('My Store')
        ->and($shop->url)->toBe('https://mystore.com')
        ->and($shop->timezone)->toBe('America/New_York')
        ->and($shop->currency)->toBe('USD')
        ->and($shop->target_roas)->toBe('4.0')
        ->and($shop->base_instructions)->toBe('Be helpful.')
        ->and($shop->brand_guidelines)->toBe('Professional tone.');
});

it('updates existing shop and dispatches saved event', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);
    $shop = Shop::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    Livewire::actingAs($user)
        ->test('pages::shop.edit')
        ->set('name', 'New Name')
        ->set('timezone', 'UTC')
        ->set('currency', 'GBP')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('shop-saved');

    expect($shop->fresh()->name)->toBe('New Name')
        ->and($shop->fresh()->currency)->toBe('GBP');
});
