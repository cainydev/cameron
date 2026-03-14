<?php

use App\Models\Shop;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('cameron'));
    $response->assertRedirect(route('login'));
});

test('authenticated users with a configured shop can visit cameron', function () {
    $user = User::factory()->create(['google_refresh_token' => 'valid-token']);
    Shop::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->get(route('cameron'));
    $response->assertOk();
});
