<?php

use App\Ai\Agents\GoalArchitect;
use App\Ai\Tools\CreateGoalFromDescription;
use App\Models\AgentGoal;
use App\Models\Shop;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    GoalArchitect::fake(function () {
        return [
            'name' => 'Keep ROAS above 3.0',
            'sensor_tool_class' => 'App\\Ai\\Tools\\GoogleAdsSensor',
            'sensor_arguments' => [],
            'conditions' => [
                ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
            ],
            'is_one_off' => false,
            'expires_at' => null,
            'initial_context' => 'User wants ROAS above 3.0.',
        ];
    });
});

it('creates an AgentGoal from a natural language context', function () {
    $tool = new CreateGoalFromDescription;

    $result = $tool->execute([
        'context' => 'I want ROAS to stay above 3.0 on my Google Ads account.',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['goal_id'])->toBeInt()
        ->and($result['definition']['sensor_tool_class'])->toBe('App\\Ai\\Tools\\GoogleAdsSensor')
        ->and($result['definition']['conditions'])->toHaveCount(1);

    $goal = AgentGoal::query()->find($result['goal_id']);

    expect($goal->name)->toBe('Keep ROAS above 3.0')
        ->and($goal->is_active)->toBeTrue();
});

it('threads shop_id onto created goal when shop is provided', function () {
    $shop = Shop::factory()->create();
    $tool = (new CreateGoalFromDescription)->forShop($shop);

    $result = $tool->execute([
        'context' => 'Monitor ROAS.',
    ]);

    $goal = AgentGoal::query()->find($result['goal_id']);

    expect($goal->shop_id)->toBe($shop->id);
});

it('creates goal with null shop_id when no shop is provided', function () {
    $tool = new CreateGoalFromDescription;

    $result = $tool->execute([
        'context' => 'Monitor ROAS.',
    ]);

    $goal = AgentGoal::query()->find($result['goal_id']);

    expect($goal->shop_id)->toBeNull();
});

it('delegates to GoalArchitect and asserts it was prompted', function () {
    $tool = new CreateGoalFromDescription;

    $tool->execute([
        'context' => 'Monitor CTR on campaign 789.',
    ]);

    GoalArchitect::assertPrompted(fn ($prompt) => $prompt->contains('Monitor CTR'));
});

it('creates a one-off goal when GoalArchitect returns is_one_off true', function () {
    GoalArchitect::fake(function () {
        return [
            'name' => 'Hit 100K revenue',
            'sensor_tool_class' => 'App\\Ai\\Tools\\RevenueSensor',
            'sensor_arguments' => [],
            'conditions' => [
                ['metric' => 'revenue', 'operator' => '>=', 'value' => 100000],
            ],
            'is_one_off' => true,
            'expires_at' => '2026-12-31T23:59:59+00:00',
            'initial_context' => 'User wants 100K revenue.',
        ];
    });

    $result = (new CreateGoalFromDescription)->execute([
        'context' => 'I want to hit 100K in revenue by end of year.',
    ]);

    $goal = AgentGoal::query()->find($result['goal_id']);

    expect($goal->is_one_off)->toBeTrue()
        ->and($goal->expires_at)->not->toBeNull();
});

it('works through the handle method via the AI SDK interface', function () {
    $tool = new CreateGoalFromDescription;

    $response = $tool->handle(new Request([
        'context' => 'Keep spend below 5000.',
    ]));

    $decoded = json_decode($response, true);

    expect($decoded['success'])->toBeTrue()
        ->and($decoded['goal_id'])->toBeInt();
});
