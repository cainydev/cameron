<?php

use App\Models\AgentGoal;
use App\Models\AgentTask;

it('can create an agent goal with conditions', function () {
    $goal = AgentGoal::factory()->create([
        'name' => 'Keep ROAS above 3.0',
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    expect($goal)
        ->name->toBe('Keep ROAS above 3.0')
        ->conditions->toBeArray()
        ->is_active->toBeTrue();
});

it('casts sensor_arguments and conditions as arrays', function () {
    $goal = AgentGoal::factory()->create([
        'sensor_arguments' => ['account_id' => 'abc123'],
        'conditions' => [['metric' => 'ctr', 'operator' => '>', 'value' => 0.02]],
    ]);

    $goal->refresh();

    expect($goal->sensor_arguments)->toBeArray()->toHaveKey('account_id')
        ->and($goal->conditions)->toBeArray()->toHaveCount(1);
});

it('has many tasks', function () {
    $goal = AgentGoal::factory()->create();

    AgentTask::factory()->count(3)->create(['goal_id' => $goal->id]);

    expect($goal->tasks)->toHaveCount(3);
});

it('can scope to active goals', function () {
    AgentGoal::factory()->count(2)->create(['is_active' => true]);
    AgentGoal::factory()->inactive()->create();

    $activeGoals = AgentGoal::query()->where('is_active', true)->get();

    expect($activeGoals)->toHaveCount(2);
});
