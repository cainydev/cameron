<?php

use App\Ai\Agents\TaskWorker;
use App\Ai\Tools\AbstractAgentTool;
use App\Jobs\CreatePlanForTask;
use App\Jobs\EvaluateSingleGoal;
use App\Jobs\RunTaskWorkerStep;
use App\Models\AgentGoal;
use App\Models\AgentTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Carbon::setTestNow('2026-06-15 12:00:00');
    Queue::fake([RunTaskWorkerStep::class, CreatePlanForTask::class]);
    TaskWorker::fake();
    app()->bind(PipelineFailingSensor::class, fn () => new PipelineFailingSensor);
});

it('dispatches CreatePlanForTask when multi-agent pipeline is enabled', function () {
    config(['cameron.use_multi_agent_pipeline' => true]);

    $goal = AgentGoal::factory()->create([
        'sensor_tool_class' => PipelineFailingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    expect(AgentTask::query()->count())->toBe(1);

    Queue::assertPushed(CreatePlanForTask::class);
    Queue::assertNotPushed(RunTaskWorkerStep::class);
});

it('dispatches RunTaskWorkerStep when multi-agent pipeline is disabled', function () {
    config(['cameron.use_multi_agent_pipeline' => false]);

    $goal = AgentGoal::factory()->create([
        'sensor_tool_class' => PipelineFailingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    expect(AgentTask::query()->count())->toBe(1);

    Queue::assertPushed(RunTaskWorkerStep::class);
    Queue::assertNotPushed(CreatePlanForTask::class);
});

it('defaults to legacy pipeline when config is not set', function () {
    config(['cameron.use_multi_agent_pipeline' => null]);

    $goal = AgentGoal::factory()->create([
        'sensor_tool_class' => PipelineFailingSensor::class,
        'conditions' => [
            ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
        ],
    ]);

    EvaluateSingleGoal::dispatchSync($goal);

    Queue::assertPushed(RunTaskWorkerStep::class);
    Queue::assertNotPushed(CreatePlanForTask::class);
});

/**
 * A fake sensor for pipeline integration tests.
 */
class PipelineFailingSensor extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    public function description(): string
    {
        return 'Fake sensor returning poor metrics for pipeline tests.';
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
