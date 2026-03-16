<?php

use App\Ai\Tools\GetGa4Traffic;
use App\Models\Shop;

it('caches responses for read-only tools', function () {
    $tool = new GetGa4Traffic;
    $shop = Shop::factory()->make(['id' => 1]);
    $tool->forShop($shop);

    $callCount = 0;
    $callback = function () use (&$callCount) {
        $callCount++;

        return ['sessions' => '100', 'pageViews' => '200'];
    };

    $ref = new ReflectionMethod($tool, 'cached');

    $args = ['startDate' => '2026-01-01', 'endDate' => '2026-01-31'];

    $result1 = $ref->invoke($tool, $args, $callback);
    $result2 = $ref->invoke($tool, $args, $callback);

    expect($callCount)->toBe(1)
        ->and($result1)->toBe($result2);
});

it('uses different cache keys for different arguments', function () {
    $tool = new GetGa4Traffic;
    $shop = Shop::factory()->make(['id' => 1]);
    $tool->forShop($shop);

    $callCount = 0;
    $callback = function () use (&$callCount) {
        $callCount++;

        return ['data' => $callCount];
    };

    $ref = new ReflectionMethod($tool, 'cached');

    $ref->invoke($tool, ['startDate' => '2026-01-01', 'endDate' => '2026-01-31'], $callback);
    $ref->invoke($tool, ['startDate' => '2026-02-01', 'endDate' => '2026-02-28'], $callback);

    expect($callCount)->toBe(2);
});

it('uses different cache keys for different shops', function () {
    $callback = function () {
        return ['data' => rand()];
    };

    $tool1 = new GetGa4Traffic;
    $tool1->forShop(Shop::factory()->make(['id' => 1]));

    $tool2 = new GetGa4Traffic;
    $tool2->forShop(Shop::factory()->make(['id' => 2]));

    $ref = new ReflectionMethod(GetGa4Traffic::class, 'cached');
    $args = ['startDate' => '2026-01-01', 'endDate' => '2026-01-31'];

    $result1 = $ref->invoke($tool1, $args, $callback);
    $result2 = $ref->invoke($tool2, $args, $callback);

    expect($result1)->not->toBe($result2);
});
