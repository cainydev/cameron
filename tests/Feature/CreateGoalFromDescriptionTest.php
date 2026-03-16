<?php

use App\Ai\Tools\CreateGoalFromDescription;
use App\Models\AgentGoal;
use App\Models\Shop;
use Laravel\Ai\Tools\Request;

$baseArgs = fn (array $overrides = []) => array_merge([
    'name' => 'Keep ROAS above 3.0',
    'sensor_tool_class' => 'App\\Ai\\Tools\\GetAccountPerformanceSummary',
    'sensor_arguments' => [],
    'conditions' => [
        ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
    ],
    'is_one_off' => false,
    'expires_at' => null,
    'check_frequency_minutes' => 60,
    'initial_context' => 'ROAS has been above 3.0 consistently.',
], $overrides);

it('creates an AgentGoal from structured arguments', function () use ($baseArgs) {
    $result = (new CreateGoalFromDescription)->execute($baseArgs());

    expect($result['success'])->toBeTrue()
        ->and($result['goal_id'])->toBeInt();

    $goal = AgentGoal::query()->find($result['goal_id']);

    expect($goal->name)->toBe('Keep ROAS above 3.0')
        ->and($goal->sensor_tool_class)->toBe('App\\Ai\\Tools\\GetAccountPerformanceSummary')
        ->and($goal->conditions)->toHaveCount(1)
        ->and($goal->check_frequency_minutes)->toBe(60)
        ->and($goal->is_active)->toBeTrue();
});

it('threads shop_id onto the goal when a shop is provided', function () use ($baseArgs) {
    $shop = Shop::factory()->create();

    $result = (new CreateGoalFromDescription)->forShop($shop)->execute($baseArgs());

    expect(AgentGoal::query()->find($result['goal_id'])->shop_id)->toBe($shop->id);
});

it('creates a goal with null shop_id when no shop is provided', function () use ($baseArgs) {
    $result = (new CreateGoalFromDescription)->execute($baseArgs());

    expect(AgentGoal::query()->find($result['goal_id'])->shop_id)->toBeNull();
});

it('creates a one-off goal with an expiry', function () use ($baseArgs) {
    $result = (new CreateGoalFromDescription)->execute($baseArgs([
        'name' => 'Hit 100K revenue',
        'is_one_off' => true,
        'expires_at' => '2026-12-31T23:59:59+00:00',
    ]));

    $goal = AgentGoal::query()->find($result['goal_id']);

    expect($goal->is_one_off)->toBeTrue()
        ->and($goal->expires_at)->not->toBeNull();
});

it('stores the provided check_frequency_minutes', function () use ($baseArgs) {
    $result = (new CreateGoalFromDescription)->execute($baseArgs(['check_frequency_minutes' => 15]));

    expect(AgentGoal::query()->find($result['goal_id'])->check_frequency_minutes)->toBe(15);
});

it('works through the handle method via the AI SDK interface', function () use ($baseArgs) {
    $response = (new CreateGoalFromDescription)->handle(new Request($baseArgs()));

    $decoded = json_decode($response, true);

    expect($decoded['success'])->toBeTrue()
        ->and($decoded['goal_id'])->toBeInt();
});
