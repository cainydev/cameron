<?php

use App\Models\User;

it('user hasGoogleConnected returns false when token is null', function () {
    $user = User::factory()->make(['google_refresh_token' => null]);

    expect($user->hasGoogleConnected())->toBeFalse();
});

it('user hasGoogleConnected returns true when token is set', function () {
    $user = User::factory()->make(['google_refresh_token' => 'some-refresh-token']);

    expect($user->hasGoogleConnected())->toBeTrue();
});

it('google_refresh_token is hidden from serialization', function () {
    $user = User::factory()->make(['google_refresh_token' => 'secret-token']);

    expect($user->toArray())->not->toHaveKey('google_refresh_token');
});

it('google_refresh_token is mass assignable', function () {
    $user = new User;
    $user->fill(['google_refresh_token' => 'fill-test-token']);

    expect($user->google_refresh_token)->toBe('fill-test-token');
});

it('google redirect route requires authentication', function () {
    $response = $this->get(route('google.redirect'));

    $response->assertRedirect(route('login'));
});

it('google callback route requires authentication', function () {
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'));
});
