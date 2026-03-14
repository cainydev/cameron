<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Tools\AbstractAgentTool;
use App\Enums\AgentTaskStatus;
use App\Models\AgentGoal;
use App\Models\AgentTask;
use App\Models\ResourceLock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates a single AgentGoal by running its sensor tool directly
 * (bypassing the AI SDK) and comparing returned metrics against conditions.
 *
 * On success: if the goal is one-off, it is marked as completed and deactivated.
 * On failure: spawns an AgentTask with a ResourceLock, injecting temporal
 * urgency into the context_payload when the goal has a deadline.
 */
class EvaluateSingleGoal implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public AgentGoal $goal) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sensorClass = $this->goal->sensor_tool_class;

        if (! class_exists($sensorClass)) {
            Log::warning("Sensor tool class [{$sensorClass}] not found for goal [{$this->goal->name}].");

            return;
        }

        $sensor = app($sensorClass);

        if ($sensor instanceof AbstractAgentTool && $this->goal->relationLoaded('shop') === false) {
            $this->goal->loadMissing('shop.user');
        }

        if ($sensor instanceof AbstractAgentTool && $this->goal->shop) {
            $sensor->forShop($this->goal->shop);
        }

        if (! $sensor instanceof AbstractAgentTool) {
            Log::warning("Sensor tool [{$sensorClass}] does not extend AbstractAgentTool.");

            return;
        }

        $sensorData = $sensor->execute($this->goal->sensor_arguments ?? []);

        if (! is_array($sensorData)) {
            Log::warning("Sensor tool [{$sensorClass}] returned non-array data for goal [{$this->goal->name}].");

            return;
        }

        if ($this->allConditionsPass($this->goal->conditions, $sensorData)) {
            $this->handleSuccess();

            return;
        }

        $this->spawnTaskForFailedGoal($sensorData);
    }

    /**
     * Handle a successful evaluation — retire one-off goals.
     */
    protected function handleSuccess(): void
    {
        Log::info("Goal [{$this->goal->name}] passed evaluation.");

        if ($this->goal->is_one_off) {
            $this->goal->update([
                'is_active' => false,
                'completed_at' => now(),
            ]);

            Log::info("One-off goal [{$this->goal->name}] completed and deactivated.");
        }
    }

    /**
     * Evaluate all conditions against the sensor data.
     *
     * @param  array<int, array{metric: string, operator: string, value: float|int|string}>  $conditions
     * @param  array<string, mixed>  $sensorData
     */
    protected function allConditionsPass(array $conditions, array $sensorData): bool
    {
        if (empty($conditions)) {
            return false;
        }

        foreach ($conditions as $condition) {
            $metric = $condition['metric'] ?? null;
            $operator = $condition['operator'] ?? null;
            $threshold = $condition['value'] ?? null;

            if ($metric === null || $operator === null || $threshold === null) {
                continue;
            }

            $actual = $sensorData[$metric] ?? null;

            if ($actual === null || ! $this->compareValues($actual, $operator, $threshold)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare an actual value against a threshold using the given operator.
     */
    protected function compareValues(mixed $actual, string $operator, mixed $threshold): bool
    {
        return match ($operator) {
            '>' => $actual > $threshold,
            '>=' => $actual >= $threshold,
            '<' => $actual < $threshold,
            '<=' => $actual <= $threshold,
            '==' => $actual == $threshold,
            '!=' => $actual != $threshold,
            default => false,
        };
    }

    /**
     * Spawn a new AgentTask and acquire a ResourceLock for the failed goal.
     * Injects temporal urgency into context_payload when the goal has a deadline.
     *
     * @param  array<string, mixed>  $sensorData
     */
    protected function spawnTaskForFailedGoal(array $sensorData): void
    {
        $resourceId = "AgentGoal:{$this->goal->id}";

        $existingLock = ResourceLock::query()
            ->where('resource_id', $resourceId)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingLock) {
            Log::info("Skipping task spawn for goal [{$this->goal->name}] — resource is already locked.");

            return;
        }

        $contextPayload = [
            'sensor_data' => $sensorData,
            'failed_conditions' => $this->goal->conditions,
            'evaluated_at' => now()->toIso8601String(),
        ];

        if ($this->goal->expires_at) {
            $contextPayload['temporal_urgency'] = $this->buildTemporalUrgencyMessage();
        }

        $task = AgentTask::query()->create([
            'goal_id' => $this->goal->id,
            'status' => AgentTaskStatus::Pending,
            'context_payload' => $contextPayload,
            'locked_resource_id' => $resourceId,
        ]);

        ResourceLock::query()->create([
            'resource_id' => $resourceId,
            'task_id' => $task->id,
            'expires_at' => now()->addHour(),
        ]);

        Log::info("Spawned AgentTask [{$task->id}] for failed goal [{$this->goal->name}].");

        RunTaskWorkerStep::dispatch($task);
    }

    /**
     * Build a temporal urgency system message for the LLM context.
     */
    protected function buildTemporalUrgencyMessage(): string
    {
        $remaining = now()->diff($this->goal->expires_at);

        $parts = [];

        if ($remaining->d > 0) {
            $parts[] = $remaining->d.' '.($remaining->d === 1 ? 'day' : 'days');
        }

        if ($remaining->h > 0) {
            $parts[] = $remaining->h.' '.($remaining->h === 1 ? 'hour' : 'hours');
        }

        if (empty($parts)) {
            $parts[] = $remaining->i.' '.($remaining->i === 1 ? 'minute' : 'minutes');
        }

        $timeLeft = implode(' and ', $parts);

        return "CRITICAL: This is a temporal goal with a strict deadline. You have {$timeLeft} remaining to fix this. Adjust your strategy and tool usage urgency accordingly.";
    }
}
