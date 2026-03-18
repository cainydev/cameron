<?php

use App\Enums\AgentRole;
use App\Enums\PlanStatus;
use App\Enums\PlanStepStatus;
use App\Enums\ToolCategory;
use App\Models\AgentGoal;
use App\Models\AgentPlan;
use App\Models\AgentPlanStep;
use App\Models\AgentTask;
use App\Models\Shop;

it('belongs to a task, goal, and shop', function () {
    $shop = Shop::factory()->create();
    $goal = AgentGoal::factory()->create(['shop_id' => $shop->id]);
    $task = AgentTask::factory()->create(['goal_id' => $goal->id]);
    $plan = AgentPlan::factory()->create([
        'task_id' => $task->id,
        'goal_id' => $goal->id,
        'shop_id' => $shop->id,
    ]);

    expect($plan->task)->toBeInstanceOf(AgentTask::class)
        ->and($plan->goal)->toBeInstanceOf(AgentGoal::class)
        ->and($plan->shop)->toBeInstanceOf(Shop::class);
});

it('casts status to PlanStatus enum', function () {
    $plan = AgentPlan::factory()->create(['status' => 'executing']);

    expect($plan->status)->toBe(PlanStatus::Executing);
});

it('casts working_memory as array', function () {
    $plan = AgentPlan::factory()->create([
        'working_memory' => ['step_1' => ['data' => 'test']],
    ]);

    $plan->refresh();

    expect($plan->working_memory)->toBeArray()->toHaveKey('step_1');
});

it('has many steps ordered by order column', function () {
    $plan = AgentPlan::factory()->create();

    AgentPlanStep::factory()->create(['plan_id' => $plan->id, 'order' => 3]);
    AgentPlanStep::factory()->create(['plan_id' => $plan->id, 'order' => 1]);
    AgentPlanStep::factory()->create(['plan_id' => $plan->id, 'order' => 2]);

    $steps = $plan->steps;

    expect($steps)->toHaveCount(3)
        ->and($steps->pluck('order')->all())->toBe([1, 2, 3]);
});

it('uses factory states for status transitions', function () {
    $planning = AgentPlan::factory()->create();
    $executing = AgentPlan::factory()->executing()->create();
    $waiting = AgentPlan::factory()->waitingApproval()->create();
    $completed = AgentPlan::factory()->completed()->create();
    $failed = AgentPlan::factory()->failed()->create();

    expect($planning->status)->toBe(PlanStatus::Planning)
        ->and($executing->status)->toBe(PlanStatus::Executing)
        ->and($waiting->status)->toBe(PlanStatus::WaitingApproval)
        ->and($completed->status)->toBe(PlanStatus::Completed)
        ->and($failed->status)->toBe(PlanStatus::Failed);
});

it('task can have a plan relationship', function () {
    $plan = AgentPlan::factory()->create();
    $task = AgentTask::factory()->create(['plan_id' => $plan->id]);

    expect($task->plan)->toBeInstanceOf(AgentPlan::class)
        ->and($task->plan->id)->toBe($plan->id);
});

it('plan step belongs to a plan', function () {
    $plan = AgentPlan::factory()->create();
    $step = AgentPlanStep::factory()->create(['plan_id' => $plan->id]);

    expect($step->plan)->toBeInstanceOf(AgentPlan::class)
        ->and($step->plan->id)->toBe($plan->id);
});

it('plan step casts specialist_role to AgentRole enum', function () {
    $step = AgentPlanStep::factory()->create(['specialist_role' => 'ads']);

    expect($step->specialist_role)->toBe(AgentRole::Ads);
});

it('plan step casts status to PlanStepStatus enum', function () {
    $step = AgentPlanStep::factory()->create(['status' => 'running']);

    expect($step->status)->toBe(PlanStepStatus::Running);
});

it('plan step can reference a dependency', function () {
    $plan = AgentPlan::factory()->create();
    $step1 = AgentPlanStep::factory()->create(['plan_id' => $plan->id, 'order' => 1]);
    $step2 = AgentPlanStep::factory()->create([
        'plan_id' => $plan->id,
        'order' => 2,
        'depends_on_step_id' => $step1->id,
    ]);

    expect($step2->dependsOn)->toBeInstanceOf(AgentPlanStep::class)
        ->and($step2->dependsOn->id)->toBe($step1->id);
});

it('plan step factory states work correctly', function () {
    $running = AgentPlanStep::factory()->running()->create();
    $completed = AgentPlanStep::factory()->completed()->create();
    $failed = AgentPlanStep::factory()->failed()->create();

    expect($running->status)->toBe(PlanStepStatus::Running)
        ->and($running->started_at)->not->toBeNull()
        ->and($completed->status)->toBe(PlanStepStatus::Completed)
        ->and($completed->completed_at)->not->toBeNull()
        ->and($failed->status)->toBe(PlanStepStatus::Failed);
});

it('agent role maps to correct tool categories', function () {
    expect(AgentRole::Analytics->toolCategories())->toBe([
        ToolCategory::GoogleAnalytics,
        ToolCategory::SearchConsole,
        ToolCategory::AccountOverview,
        ToolCategory::Website,
    ])->and(AgentRole::Ads->toolCategories())->toBe([
        ToolCategory::GoogleAds,
    ])->and(AgentRole::Catalog->toolCategories())->toBe([
        ToolCategory::Shopware,
    ])->and(AgentRole::System->toolCategories())->toBe([
        ToolCategory::System,
        ToolCategory::Memory,
    ]);
});

it('cascades delete from plan to steps', function () {
    $plan = AgentPlan::factory()->create();
    AgentPlanStep::factory()->count(3)->create(['plan_id' => $plan->id]);

    $plan->delete();

    expect(AgentPlanStep::query()->where('plan_id', $plan->id)->count())->toBe(0);
});
