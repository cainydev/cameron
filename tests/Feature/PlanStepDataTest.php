<?php

use App\Ai\Data\PlanStepData;
use App\Enums\AgentRole;

it('creates a valid PlanStepData from array', function () {
    $data = PlanStepData::fromArray([
        'order' => 1,
        'role' => 'analytics',
        'action' => 'Fetch GA4 traffic data for the last 30 days',
        'depends_on' => null,
        'on_failure' => 'retry',
    ]);

    expect($data->order)->toBe(1)
        ->and($data->role)->toBe(AgentRole::Analytics)
        ->and($data->action)->toBe('Fetch GA4 traffic data for the last 30 days')
        ->and($data->dependsOn)->toBeNull()
        ->and($data->onFailure)->toBe('retry');
});

it('rejects invalid role', function () {
    PlanStepData::fromArray([
        'order' => 1,
        'role' => 'nonexistent',
        'action' => 'This should fail validation',
        'depends_on' => null,
        'on_failure' => 'retry',
    ]);
})->throws(InvalidArgumentException::class);

it('rejects missing required fields', function () {
    PlanStepData::fromArray([
        'order' => 1,
    ]);
})->throws(InvalidArgumentException::class);

it('rejects action that is too short', function () {
    PlanStepData::fromArray([
        'order' => 1,
        'role' => 'ads',
        'action' => 'Too short',
        'depends_on' => null,
        'on_failure' => 'retry',
    ]);
})->throws(InvalidArgumentException::class);

it('rejects invalid on_failure strategy', function () {
    PlanStepData::fromArray([
        'order' => 1,
        'role' => 'ads',
        'action' => 'This is a valid action description',
        'depends_on' => null,
        'on_failure' => 'invalid_strategy',
    ]);
})->throws(InvalidArgumentException::class);

it('validates a full list of steps', function () {
    $steps = PlanStepData::validateSteps([
        ['order' => 1, 'role' => 'analytics', 'action' => 'Fetch GA4 traffic sources for the last 30 days', 'depends_on' => null, 'on_failure' => 'retry'],
        ['order' => 2, 'role' => 'ads', 'action' => 'Pause underperforming campaigns based on analytics data', 'depends_on' => 1, 'on_failure' => 'escalate'],
        ['order' => 3, 'role' => 'ads', 'action' => 'Add negative keywords for wasting search terms', 'depends_on' => 1, 'on_failure' => 'escalate'],
    ]);

    expect($steps)->toHaveCount(3)
        ->and($steps[0]->order)->toBe(1)
        ->and($steps[1]->order)->toBe(2)
        ->and($steps[2]->order)->toBe(3);
});

it('rejects empty step list', function () {
    PlanStepData::validateSteps([]);
})->throws(InvalidArgumentException::class, 'at least one step');

it('rejects more than 8 steps', function () {
    $steps = array_map(fn ($i) => [
        'order' => $i,
        'role' => 'analytics',
        'action' => "Step number {$i} with a valid action description",
        'depends_on' => null,
        'on_failure' => 'retry',
    ], range(1, 9));

    PlanStepData::validateSteps($steps);
})->throws(InvalidArgumentException::class, 'not exceed 8');

it('rejects duplicate order numbers', function () {
    PlanStepData::validateSteps([
        ['order' => 1, 'role' => 'analytics', 'action' => 'First step with valid action text', 'depends_on' => null, 'on_failure' => 'retry'],
        ['order' => 1, 'role' => 'ads', 'action' => 'Duplicate order with valid action text', 'depends_on' => null, 'on_failure' => 'retry'],
    ]);
})->throws(InvalidArgumentException::class, 'Duplicate step order');

it('rejects dependency on undefined step', function () {
    PlanStepData::validateSteps([
        ['order' => 1, 'role' => 'analytics', 'action' => 'First step with valid action text here', 'depends_on' => 5, 'on_failure' => 'retry'],
    ]);
})->throws(InvalidArgumentException::class, 'has not been defined');

it('sorts steps by order', function () {
    $steps = PlanStepData::validateSteps([
        ['order' => 3, 'role' => 'ads', 'action' => 'Third step with valid action text here', 'depends_on' => null, 'on_failure' => 'escalate'],
        ['order' => 1, 'role' => 'analytics', 'action' => 'First step with valid action text here', 'depends_on' => null, 'on_failure' => 'retry'],
        ['order' => 2, 'role' => 'catalog', 'action' => 'Second step with valid action text here', 'depends_on' => 1, 'on_failure' => 'retry'],
    ]);

    expect($steps[0]->order)->toBe(1)
        ->and($steps[1]->order)->toBe(2)
        ->and($steps[2]->order)->toBe(3);
});
