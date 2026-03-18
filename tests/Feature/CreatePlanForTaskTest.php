<?php

use App\Ai\Agents\Planner;
use App\Enums\PlanStatus;
use App\Enums\PlanStepStatus;
use App\Jobs\CreatePlanForTask;
use App\Jobs\RunPlanStep;
use App\Models\AgentGoal;
use App\Models\AgentPlan;
use App\Models\AgentTask;
use App\Models\Shop;
use Illuminate\Support\Facades\Queue;

it('creates a plan with steps from planner output', function () {
    Queue::fake([RunPlanStep::class]);

    $shop = Shop::factory()->create();
    $goal = AgentGoal::factory()->create(['shop_id' => $shop->id, 'name' => 'Keep ROAS above 3.0']);
    $task = AgentTask::factory()->create([
        'goal_id' => $goal->id,
        'context_payload' => [
            'sensor_data' => ['roas' => 1.8, 'spend' => 500],
            'failed_conditions' => [['metric' => 'roas', 'operator' => '>=', 'value' => 3.0]],
        ],
    ]);

    Planner::fake(function () {
        return [
            'steps' => [
                ['order' => 1, 'role' => 'analytics', 'action' => 'Fetch GA4 traffic sources for the last 30 days to identify drop', 'depends_on' => null, 'on_failure' => 'retry'],
                ['order' => 2, 'role' => 'ads', 'action' => 'Pause campaigns with ROAS below 1.0 based on analytics findings', 'depends_on' => 1, 'on_failure' => 'escalate'],
            ],
        ];
    });

    (new CreatePlanForTask($task))->handle();

    $task->refresh();

    expect($task->plan_id)->not->toBeNull();

    $plan = AgentPlan::query()->where('task_id', $task->id)->first();

    expect($plan)->not->toBeNull()
        ->and($plan->status)->toBe(PlanStatus::Executing)
        ->and($plan->objective)->toBe('Keep ROAS above 3.0');

    $steps = $plan->steps;

    expect($steps)->toHaveCount(2)
        ->and($steps[0]->specialist_role->value)->toBe('analytics')
        ->and($steps[0]->status)->toBe(PlanStepStatus::Pending)
        ->and($steps[1]->specialist_role->value)->toBe('ads')
        ->and($steps[1]->depends_on_step_id)->toBe($steps[0]->id);

    Queue::assertPushed(RunPlanStep::class);
});

it('creates a fallback plan when planner output is invalid', function () {
    Queue::fake([RunPlanStep::class]);

    $shop = Shop::factory()->create();
    $goal = AgentGoal::factory()->create(['shop_id' => $shop->id]);
    $task = AgentTask::factory()->create([
        'goal_id' => $goal->id,
        'context_payload' => [
            'sensor_data' => ['roas' => 1.2],
            'failed_conditions' => [['metric' => 'roas', 'operator' => '>=', 'value' => 3.0]],
        ],
    ]);

    Planner::fake(function () {
        return ['steps' => []];
    });

    (new CreatePlanForTask($task))->handle();

    $plan = AgentPlan::query()->where('task_id', $task->id)->first();

    expect($plan)->not->toBeNull()
        ->and($plan->status)->toBe(PlanStatus::Executing)
        ->and($plan->retry_count)->toBe(3);

    $steps = $plan->steps;

    expect($steps)->toHaveCount(1)
        ->and($steps[0]->specialist_role->value)->toBe('ads')
        ->and($steps[0]->on_failure)->toBe('halt');

    Queue::assertPushed(RunPlanStep::class);
});

it('skips execution for tasks in terminal states', function () {
    Queue::fake([RunPlanStep::class]);

    $task = AgentTask::factory()->completed()->create([
        'context_payload' => ['sensor_data' => [], 'failed_conditions' => []],
    ]);

    Planner::fake();

    (new CreatePlanForTask($task))->handle();

    expect(AgentPlan::query()->where('task_id', $task->id)->count())->toBe(0);

    Planner::assertNeverPrompted();
    Queue::assertNothingPushed();
});
