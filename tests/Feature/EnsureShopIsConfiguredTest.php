<?php

use App\Models\Shop;
use App\Models\User;

it('redirects to setup when google is not connected', function () {
    $user = User::factory()->create(['google_refresh_token' => null]);
    Shop::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('cameron'))
        ->assertRedirect('/setup');
});

it('redirects to shop edit when google is connected but no shop exists', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);

    $this->actingAs($user)
        ->get(route('cameron'))
        ->assertRedirect(route('shop.edit'));
});

it('redirects to setup when both google and shop are missing', function () {
    $user = User::factory()->create(['google_refresh_token' => null]);

    $this->actingAs($user)
        ->get(route('cameron'))
        ->assertRedirect('/setup');
});
