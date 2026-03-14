<?php

use App\Ai\Agents\GoalArchitect;
use App\Ai\Tools\CreateGoalFromDescription;
use App\Models\AgentGoal;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    GoalArchitect::fake(function () {
        return [
            'sensor_tool_class' => 'App\\Ai\\Tools\\GoogleAdsSensor',
            'sensor_arguments' => ['account_id' => '12345'],
            'conditions' => [
                ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
            ],
            'is_one_off' => false,
            'expires_at' => null,
        ];
    });
});

it('creates an AgentGoal from a natural language description', function () {
    $tool = new CreateGoalFromDescription;

    $result = $tool->execute([
        'name' => 'Keep ROAS above 3.0',
        'description' => 'I want ROAS to stay above 3.0 on my Google Ads account 12345.',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['goal_id'])->toBeInt()
        ->and($result['definition']['sensor_tool_class'])->toBe('App\\Ai\\Tools\\GoogleAdsSensor')
        ->and($result['definition']['conditions'])->toHaveCount(1);

    $goal = AgentGoal::query()->find($result['goal_id']);

    expect($goal->name)->toBe('Keep ROAS above 3.0')
        ->and($goal->is_active)->toBeTrue()
        ->and($goal->sensor_arguments)->toBe(['account_id' => '12345']);
});

it('delegates to GoalArchitect and asserts it was prompted', function () {
    $tool = new CreateGoalFromDescription;

    $tool->execute([
        'name' => 'Test Goal',
        'description' => 'Monitor CTR on campaign 789.',
    ]);

    GoalArchitect::assertPrompted(fn ($prompt) => $prompt->contains('Monitor CTR'));
});

it('creates a one-off goal when GoalArchitect returns is_one_off true', function () {
    GoalArchitect::fake(function () {
        return [
            'sensor_tool_class' => 'App\\Ai\\Tools\\RevenueSensor',
            'sensor_arguments' => [],
            'conditions' => [
                ['metric' => 'revenue', 'operator' => '>=', 'value' => 100000],
            ],
            'is_one_off' => true,
            'expires_at' => '2026-12-31T23:59:59+00:00',
        ];
    });

    $result = (new CreateGoalFromDescription)->execute([
        'name' => 'Hit 100K revenue',
        'description' => 'I want to hit 100K in revenue by end of year.',
    ]);

    $goal = AgentGoal::query()->find($result['goal_id']);

    expect($goal->is_one_off)->toBeTrue()
        ->and($goal->expires_at)->not->toBeNull();
});

it('works through the handle method via the AI SDK interface', function () {
    $tool = new CreateGoalFromDescription;

    $response = $tool->handle(new Request([
        'name' => 'Handle test',
        'description' => 'Keep spend below 5000.',
    ]));

    $decoded = json_decode($response, true);

    expect($decoded['success'])->toBeTrue()
        ->and($decoded['goal_id'])->toBeInt();
});
