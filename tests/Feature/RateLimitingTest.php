<?php

use App\Ai\Tools\GetGa4Traffic;
use App\Models\Shop;
use Illuminate\Support\Facades\RateLimiter;

it('blocks after exceeding the rate limit', function () {
    $shop = Shop::factory()->make(['id' => 99]);
    $tool = new GetGa4Traffic;
    $tool->forShop($shop);

    $key = 'google_api:google_analytics:99';

    // Hit the rate limit 10 times (GA4 limit)
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($key, 60);
    }

    $ref = new ReflectionMethod($tool, 'checkRateLimit');

    expect(fn () => $ref->invoke($tool))
        ->toThrow(RuntimeException::class, 'Rate limit exceeded');
});

it('does not block when under the limit', function () {
    $shop = Shop::factory()->make(['id' => 1]);
    $key = 'google_api:google_analytics:1';
    RateLimiter::clear($key);

    $tool = new GetGa4Traffic;
    $tool->forShop($shop);

    $ref = new ReflectionMethod($tool, 'checkRateLimit');

    // Should not throw
    $ref->invoke($tool);

    expect(true)->toBeTrue();
});
