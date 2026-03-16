<?php

use App\Ai\Agents\TaskWorker;
use App\Ai\Tools\AbstractAgentTool;
use App\Enums\AgentTaskStatus;
use App\Jobs\EvaluateSingleGoal;
use App\Jobs\RunTaskWorkerStep;
use App\Models\AgentGoal;
use App\Models\AgentTask;
use App\Models\ResourceLock;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Carbon::setTestNow('2026-06-15 12:00:00');
    Queue::fake([RunTaskWorkerStep::class]);
    TaskWorker::fake();
    app()->bind(FakePassingSensor::class, fn () => new FakePassingSensor);
    app()->bind(FakeFailingSensor::class, fn () => new FakeFailingSensor);
});

it('passes evaluation and does nothing for a regular goal', function () {
    $goal = AgentGoal::factory()->create([
        'sensor_tool_class' => FakePassingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    expect(AgentTask::query()->count())->toBe(0)
        ->and($goal->refresh()->is_active)->toBeTrue();
});

it('deactivates and completes a one-off goal on success', function () {
    $goal = AgentGoal::factory()->oneOff()->create([
        'sensor_tool_class' => FakePassingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    $goal->refresh();

    expect($goal->is_active)->toBeFalse()
        ->and($goal->completed_at)->not->toBeNull();
});

it('does not deactivate a regular goal on success', function () {
    $goal = AgentGoal::factory()->create([
        'sensor_tool_class' => FakePassingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    expect($goal->refresh()->is_active)->toBeTrue()
        ->and($goal->completed_at)->toBeNull();
});

it('spawns a task when conditions fail', function () {
    $goal = AgentGoal::factory()->create([
        'sensor_tool_class' => FakeFailingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    expect(AgentTask::query()->count())->toBe(1)
        ->and(ResourceLock::query()->count())->toBe(1);

    $task = AgentTask::query()->first();
    expect($task->status)->toBe(AgentTaskStatus::Pending)
        ->and($task->context_payload)->toHaveKey('sensor_data')
        ->and($task->context_payload)->toHaveKey('failed_conditions');

    Queue::assertPushed(RunTaskWorkerStep::class, fn ($job) => $job->task->id === $task->id);
});

it('injects temporal urgency into context when goal has a deadline', function () {
    $goal = AgentGoal::factory()->temporal(now()->addDays(3)->addHours(4))->create([
        'sensor_tool_class' => FakeFailingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    $task = AgentTask::query()->first();

    expect($task->context_payload)
        ->toHaveKey('temporal_urgency')
        ->and($task->context_payload['temporal_urgency'])
        ->toContain('CRITICAL')
        ->toContain('3 days')
        ->toContain('4 hours');
});

it('does not inject temporal urgency when goal has no deadline', function () {
    $goal = AgentGoal::factory()->create([
        'sensor_tool_class' => FakeFailingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    $task = AgentTask::query()->first();

    expect($task->context_payload)->not->toHaveKey('temporal_urgency');
});

it('does not spawn duplicate tasks when resource is already locked', function () {
    $goal = AgentGoal::factory()->create([
        'sensor_tool_class' => FakeFailingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);
    EvaluateSingleGoal::dispatchSync($goal);

    expect(AgentTask::query()->count())->toBe(1);
});

it('stamps last_checked_at after evaluation', function () {
    $goal = AgentGoal::factory()->create([
        'sensor_tool_class' => FakePassingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
        'last_checked_at' => null,
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    expect($goal->refresh()->last_checked_at)->not->toBeNull()
        ->and($goal->last_checked_at->toDateTimeString())->toBe('2026-06-15 12:00:00');
});

it('skips goals with invalid sensor classes gracefully', function () {
    $goal = AgentGoal::factory()->create([
        'sensor_tool_class' => 'App\\Ai\\Tools\\NonExistentSensor',
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    expect(AgentTask::query()->count())->toBe(0);
});

/**
 * A fake sensor that returns metrics passing typical ROAS conditions.
 */
class FakePassingSensor extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    public function description(): string
    {
        return 'Fake sensor returning healthy metrics.';
    }

    public function execute(array $arguments): array
    {
        return ['roas' => 4.2, 'ctr' => 0.05, 'spend' => 1200.00];
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

/**
 * A fake sensor that returns metrics failing typical ROAS conditions.
 */
class FakeFailingSensor extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    public function description(): string
    {
        return 'Fake sensor returning poor metrics.';
    }

    public function execute(array $arguments): array
    {
        return ['roas' => 1.2, 'ctr' => 0.01, 'spend' => 5000.00];
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
