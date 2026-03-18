<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\Planner;
use App\Ai\Data\PlanStepData;
use App\Enums\AgentRole;
use App\Enums\AgentTaskStatus;
use App\Enums\PlanStatus;
use App\Enums\PlanStepStatus;
use App\Events\TaskStatusUpdated;
use App\Models\AgentPlan;
use App\Models\AgentTask;
use App\Models\ResourceLock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Ai\Enums\Lab;
use Throwable;

class CreatePlanForTask implements ShouldQueue
{
    use Queueable;

    private const int MAX_RETRIES = 2;

    public function __construct(
        public AgentTask $task,
        public ?string $revisionHints = null,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("plan_for_task_{$this->task->id}"))->dontRelease(),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = $this->task->fresh();

        if (in_array($task->status, [AgentTaskStatus::Aborted, AgentTaskStatus::Completed, AgentTaskStatus::Failed], true)) {
            return;
        }

        $task->loadMissing('goal.shop.user');
        $goal = $task->goal;

        $plan = AgentPlan::query()->create([
            'task_id' => $task->id,
            'goal_id' => $goal?->id,
            'shop_id' => $goal?->shop_id,
            'status' => PlanStatus::Planning,
            'objective' => $goal?->name ?? 'Address failed goal conditions',
            'working_memory' => [],
        ]);

        $task->update(['plan_id' => $plan->id]);

        $shopContext = $this->buildShopContext($goal?->shop);

        $planner = new Planner(
            objective: $plan->objective,
            sensorData: $task->context_payload['sensor_data'] ?? [],
            failedConditions: $task->context_payload['failed_conditions'] ?? [],
            shopContext: $shopContext,
            initialContext: $goal?->initial_context,
            revisionHints: $this->revisionHints,
        );

        [$provider, $model] = app()->environment('local')
            ? [Lab::Gemini, 'gemini-2.0-flash']
            : [Lab::Gemini, 'gemini-2.0-flash'];

        $lastException = null;
        $retryCount = 0;

        while ($retryCount <= self::MAX_RETRIES) {
            try {
                $response = $planner->prompt(
                    'Analyze the sensor data and failed conditions. Produce an execution plan.',
                    provider: $provider,
                    model: $model,
                );

                $steps = PlanStepData::validateSteps($response['steps'] ?? []);

                $this->persistSteps($plan, $steps);

                $plan->update([
                    'status' => PlanStatus::Executing,
                    'retry_count' => $retryCount,
                ]);

                $firstStep = $plan->steps()->orderBy('order')->first();

                if ($firstStep) {
                    RunPlanStep::dispatch($task, $firstStep);
                }

                Log::info("Created plan [{$plan->id}] with ".count($steps)." steps for task [{$task->id}].");

                return;
            } catch (InvalidArgumentException $e) {
                $lastException = $e;
                $retryCount++;
                Log::warning("Planner output validation failed (attempt {$retryCount}): {$e->getMessage()}");
            }
        }

        Log::warning('Planner failed after '.self::MAX_RETRIES." retries for task [{$task->id}]. Creating fallback plan.");
        $this->createFallbackPlan($plan, $task);
    }

    /**
     * Persist validated plan steps to the database.
     *
     * @param  list<PlanStepData>  $steps
     */
    protected function persistSteps(AgentPlan $plan, array $steps): void
    {
        $orderToId = [];

        foreach ($steps as $dto) {
            $step = $plan->steps()->create([
                'order' => $dto->order,
                'specialist_role' => $dto->role,
                'action' => $dto->action,
                'depends_on_step_id' => $dto->dependsOn !== null ? ($orderToId[$dto->dependsOn] ?? null) : null,
                'status' => PlanStepStatus::Pending,
                'on_failure' => $dto->onFailure,
            ]);

            $orderToId[$dto->order] = $step->id;
        }
    }

    /**
     * Create a single-step fallback plan when the Planner fails to produce valid output.
     */
    protected function createFallbackPlan(AgentPlan $plan, AgentTask $task): void
    {
        $failedConditions = $task->context_payload['failed_conditions'] ?? [];
        $conditionSummary = json_encode($failedConditions, JSON_UNESCAPED_SLASHES);

        $plan->steps()->create([
            'order' => 1,
            'specialist_role' => AgentRole::Ads,
            'action' => "Investigate and address the following failed conditions: {$conditionSummary}. "
                .'Analyze the relevant metrics, identify root causes, and take corrective action.',
            'status' => PlanStepStatus::Pending,
            'on_failure' => 'halt',
        ]);

        $plan->update([
            'status' => PlanStatus::Executing,
            'retry_count' => self::MAX_RETRIES + 1,
        ]);

        $firstStep = $plan->steps()->orderBy('order')->first();

        if ($firstStep) {
            RunPlanStep::dispatch($task, $firstStep);
        }

        Log::info("Created fallback plan [{$plan->id}] for task [{$task->id}].");
    }

    protected function buildShopContext(mixed $shop): ?string
    {
        if (! $shop) {
            return null;
        }

        return implode("\n", array_filter([
            "- Shop: {$shop->name}",
            $shop->url ? "- Website: {$shop->url}" : null,
            "- Timezone: {$shop->timezone}",
            "- Currency: {$shop->currency}",
            $shop->target_roas ? "- Target ROAS: {$shop->target_roas}" : null,
        ]));
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $e): void
    {
        $task = $this->task->fresh();

        if ($task && ! in_array($task->status, [AgentTaskStatus::Completed, AgentTaskStatus::Aborted], true)) {
            $task->update(['status' => AgentTaskStatus::Failed]);
            ResourceLock::query()->where('task_id', $task->id)->delete();
            TaskStatusUpdated::dispatch($task->load('conversation'), AgentTaskStatus::Failed, $e->getMessage());
        }

        Log::error("CreatePlanForTask failed for Task [{$this->task->id}]: {$e->getMessage()}");
    }
}
