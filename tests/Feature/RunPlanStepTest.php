<?php

use App\Ai\Agents\Specialist;
use App\Enums\AgentRole;
use App\Enums\AgentTaskStatus;
use App\Enums\PlanStatus;
use App\Enums\PlanStepStatus;
use App\Jobs\CreatePlanForTask;
use App\Jobs\RunPlanStep;
use App\Models\AgentGoal;
use App\Models\AgentPlan;
use App\Models\AgentPlanStep;
use App\Models\AgentTask;
use App\Models\ResourceLock;
use App\Models\Shop;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->goal = AgentGoal::factory()->create(['shop_id' => $this->shop->id]);
    $this->task = AgentTask::factory()->create([
        'goal_id' => $this->goal->id,
        'context_payload' => [
            'sensor_data' => ['roas' => 1.5],
            'failed_conditions' => [['metric' => 'roas', 'operator' => '>=', 'value' => 3.0]],
        ],
    ]);
    $this->plan = AgentPlan::factory()->executing()->create([
        'task_id' => $this->task->id,
        'goal_id' => $this->goal->id,
        'shop_id' => $this->shop->id,
    ]);
    $this->task->update(['plan_id' => $this->plan->id]);
});

it('skips execution for tasks in terminal states', function () {
    Specialist::fake();

    $step = AgentPlanStep::factory()->create(['plan_id' => $this->plan->id, 'order' => 1]);
    $this->task->update(['status' => AgentTaskStatus::Completed]);

    (new RunPlanStep($this->task, $step))->handle();

    Specialist::assertNeverPrompted();
    expect($step->fresh()->status)->toBe(PlanStepStatus::Pending);
});

it('skips execution for completed steps', function () {
    Specialist::fake();

    $step = AgentPlanStep::factory()->completed()->create(['plan_id' => $this->plan->id, 'order' => 1]);

    (new RunPlanStep($this->task, $step))->handle();

    Specialist::assertNeverPrompted();
});

it('skips execution for steps waiting for approval', function () {
    Specialist::fake();

    $step = AgentPlanStep::factory()->create([
        'plan_id' => $this->plan->id,
        'order' => 1,
        'status' => PlanStepStatus::WaitingApproval,
    ]);

    (new RunPlanStep($this->task, $step))->handle();

    Specialist::assertNeverPrompted();
});

it('marks step as running when starting from pending', function () {
    Queue::fake([RunPlanStep::class]);
    Specialist::fake(['Step executed successfully.']);

    $step = AgentPlanStep::factory()->create([
        'plan_id' => $this->plan->id,
        'order' => 1,
        'specialist_role' => AgentRole::Analytics,
    ]);

    (new RunPlanStep($this->task, $step))->handle();

    expect($step->fresh()->status)->toBe(PlanStepStatus::Running)
        ->and($step->fresh()->started_at)->not->toBeNull();
});

it('fails step when max iterations exceeded', function () {
    Specialist::fake();

    $step = AgentPlanStep::factory()->running()->create([
        'plan_id' => $this->plan->id,
        'order' => 1,
        'on_failure' => 'halt',
    ]);

    ResourceLock::factory()->create(['task_id' => $this->task->id]);

    (new RunPlanStep($this->task, $step, stepCount: 30))->handle();

    expect($step->fresh()->status)->toBe(PlanStepStatus::Failed)
        ->and($this->plan->fresh()->status)->toBe(PlanStatus::Failed)
        ->and($this->task->fresh()->status)->toBe(AgentTaskStatus::Failed)
        ->and(ResourceLock::query()->where('task_id', $this->task->id)->count())->toBe(0);

    Specialist::assertNeverPrompted();
});

it('retries step on failure when strategy is retry', function () {
    Queue::fake([RunPlanStep::class]);
    Specialist::fake();

    $step = AgentPlanStep::factory()->running()->create([
        'plan_id' => $this->plan->id,
        'order' => 1,
        'on_failure' => 'retry',
        'retry_count' => 0,
    ]);

    (new RunPlanStep($this->task, $step, stepCount: 30))->handle();

    expect($step->fresh()->retry_count)->toBe(1);
    Queue::assertPushed(RunPlanStep::class);
});

it('escalates to re-plan when strategy is escalate', function () {
    Queue::fake([CreatePlanForTask::class]);
    Specialist::fake();

    $step = AgentPlanStep::factory()->running()->create([
        'plan_id' => $this->plan->id,
        'order' => 1,
        'on_failure' => 'escalate',
    ]);

    (new RunPlanStep($this->task, $step, stepCount: 30))->handle();

    expect($step->fresh()->status)->toBe(PlanStepStatus::Failed)
        ->and($this->plan->fresh()->status)->toBe(PlanStatus::Failed);

    Queue::assertPushed(CreatePlanForTask::class, function ($job) {
        return $job->revisionHints !== null;
    });
});

it('advances to next step after completing current one', function () {
    Queue::fake([RunPlanStep::class]);

    Specialist::fake(['Analysis complete: ROAS dropped due to high CPC on campaign 123.']);

    $step1 = AgentPlanStep::factory()->create([
        'plan_id' => $this->plan->id,
        'order' => 1,
        'specialist_role' => AgentRole::Analytics,
    ]);
    $step2 = AgentPlanStep::factory()->create([
        'plan_id' => $this->plan->id,
        'order' => 2,
        'specialist_role' => AgentRole::Ads,
    ]);

    // Simulate the agent completing immediately by setting task to completed after prompt
    Specialist::fake(function () {
        $this->task->update(['status' => AgentTaskStatus::Completed]);

        return 'Analysis complete.';
    });

    (new RunPlanStep($this->task, $step1))->handle();

    $step1 = $step1->fresh();

    expect($step1->status)->toBe(PlanStepStatus::Completed)
        ->and($step1->output_summary)->not->toBeNull()
        ->and($step1->completed_at)->not->toBeNull();

    $plan = $this->plan->fresh();
    expect($plan->working_memory)->toHaveKey('step_1');

    Queue::assertPushed(RunPlanStep::class, function ($job) use ($step2) {
        return $job->step->id === $step2->id;
    });
});

it('completes plan when last step finishes', function () {
    Queue::fake([RunPlanStep::class]);

    $step = AgentPlanStep::factory()->create([
        'plan_id' => $this->plan->id,
        'order' => 1,
        'specialist_role' => AgentRole::Ads,
    ]);

    ResourceLock::factory()->create(['task_id' => $this->task->id]);

    Specialist::fake(function () {
        $this->task->update(['status' => AgentTaskStatus::Completed]);

        return 'Campaign paused successfully.';
    });

    (new RunPlanStep($this->task, $step))->handle();

    expect($this->plan->fresh()->status)->toBe(PlanStatus::Completed)
        ->and($this->task->fresh()->status)->toBe(AgentTaskStatus::Completed)
        ->and(ResourceLock::query()->where('task_id', $this->task->id)->count())->toBe(0);

    Queue::assertNotPushed(RunPlanStep::class);
});
