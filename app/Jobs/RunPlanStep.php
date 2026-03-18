<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\Specialist;
use App\Enums\AgentTaskStatus;
use App\Enums\PlanStatus;
use App\Enums\PlanStepStatus;
use App\Events\TaskStatusUpdated;
use App\Models\AgentPlanStep;
use App\Models\AgentTask;
use App\Models\ResourceLock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Throwable;

class RunPlanStep implements ShouldQueue
{
    use Queueable;

    private const int MAX_STEP_ITERATIONS = 30;

    private const int MAX_STEP_RETRIES = 2;

    private const int WORKING_MEMORY_TRUNCATE = 2000;

    public function __construct(
        public AgentTask $task,
        public AgentPlanStep $step,
        public int $stepCount = 0,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("plan_step_{$this->step->id}"))->dontRelease(),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = $this->task->fresh();
        $step = $this->step->fresh();
        $plan = $step->plan;

        if ($this->stepCount >= self::MAX_STEP_ITERATIONS) {
            $this->failStep($step, $plan, $task, 'Step exceeded maximum iteration limit.');

            return;
        }

        if (in_array($task->status, [AgentTaskStatus::Aborted, AgentTaskStatus::Completed, AgentTaskStatus::Failed], true)) {
            return;
        }

        if (in_array($plan->status, [PlanStatus::Aborted, PlanStatus::Failed, PlanStatus::Completed], true)) {
            return;
        }

        if (in_array($step->status, [PlanStepStatus::Completed, PlanStepStatus::Failed, PlanStepStatus::Skipped], true)) {
            return;
        }

        if ($step->status === PlanStepStatus::WaitingApproval) {
            return;
        }

        if ($step->status === PlanStepStatus::Pending) {
            $step->update([
                'status' => PlanStepStatus::Running,
                'started_at' => now(),
            ]);
        }

        $task->update(['status' => AgentTaskStatus::Running]);
        TaskStatusUpdated::dispatch($task->load('conversation'), AgentTaskStatus::Running);

        $task->loadMissing('goal.shop.user');
        $shop = $task->goal?->shop;

        if (! $shop) {
            $this->failStep($step, $plan, $task, 'No shop context available.');

            return;
        }

        $specialist = new Specialist(
            role: $step->specialist_role,
            shop: $shop,
            stepInstruction: $step->action,
            taskId: $task->id,
            workingMemory: $plan->working_memory ?? [],
            goalContext: json_encode($task->context_payload['sensor_data'] ?? [], JSON_THROW_ON_ERROR),
            urgencyDeadline: $task->context_payload['temporal_urgency'] ?? null,
        );

        [$provider, $model] = app()->environment('local')
            ? [Lab::Gemini, 'gemini-2.0-flash']
            : [Lab::DeepSeek, 'deepseek-chat'];

        if ($step->conversation_id) {
            $specialist->continue($step->conversation_id, (object) ['id' => null]);
        } else {
            $specialist->forUser((object) ['id' => null]);
        }

        $response = $specialist->prompt(
            $this->stepCount === 0 ? 'Execute the task described in your instructions.' : '[SYSTEM] Next step.',
            provider: $provider,
            model: $model,
        );

        if (! $step->conversation_id && $response->conversationId) {
            $step->update(['conversation_id' => $response->conversationId]);
        }

        $task->refresh();
        $step->refresh();

        if ($task->status === AgentTaskStatus::WaitingApproval || $step->status === PlanStepStatus::WaitingApproval) {
            $step->update(['status' => PlanStepStatus::WaitingApproval]);
            $plan->update(['status' => PlanStatus::WaitingApproval]);

            return;
        }

        if (in_array($task->status, [AgentTaskStatus::Completed, AgentTaskStatus::Aborted, AgentTaskStatus::Failed], true)) {
            $this->completeStep($step, $plan, $task, $response->text ?? '');

            return;
        }

        self::dispatch($task, $step, $this->stepCount + 1);
    }

    /**
     * Mark the step as completed and advance to the next step or finish the plan.
     */
    protected function completeStep(AgentPlanStep $step, mixed $plan, AgentTask $task, string $responseText): void
    {
        $summary = Str::limit($responseText, self::WORKING_MEMORY_TRUNCATE);

        $step->update([
            'status' => PlanStepStatus::Completed,
            'output_summary' => $summary,
            'completed_at' => now(),
        ]);

        $workingMemory = $plan->working_memory ?? [];
        $workingMemory["step_{$step->order}"] = $summary;
        $plan->update(['working_memory' => $workingMemory]);

        $task->update(['status' => AgentTaskStatus::Running]);

        $nextStep = $plan->steps()
            ->where('order', '>', $step->order)
            ->where('status', PlanStepStatus::Pending)
            ->orderBy('order')
            ->first();

        if ($nextStep) {
            self::dispatch($task, $nextStep);
        } else {
            $this->completePlan($plan, $task);
        }
    }

    /**
     * Mark the plan as completed and release resources.
     */
    protected function completePlan(mixed $plan, AgentTask $task): void
    {
        $plan->update(['status' => PlanStatus::Completed]);
        $task->update(['status' => AgentTaskStatus::Completed]);

        ResourceLock::query()->where('task_id', $task->id)->delete();

        TaskStatusUpdated::dispatch($task->load('conversation'), AgentTaskStatus::Completed);

        Log::info("Plan [{$plan->id}] completed for task [{$task->id}].");
    }

    /**
     * Handle a step failure according to its on_failure strategy.
     */
    protected function failStep(AgentPlanStep $step, mixed $plan, AgentTask $task, string $reason): void
    {
        $strategy = $step->on_failure ?? 'retry';

        if ($strategy === 'retry' && $step->retry_count < self::MAX_STEP_RETRIES) {
            $step->update(['retry_count' => $step->retry_count + 1]);
            Log::warning("Retrying step [{$step->id}] (attempt {$step->retry_count}): {$reason}");
            self::dispatch($task, $step, 0);

            return;
        }

        if ($strategy === 'escalate') {
            $step->update(['status' => PlanStepStatus::Failed]);
            $plan->update(['status' => PlanStatus::Failed]);
            $task->update(['status' => AgentTaskStatus::Failed]);

            ResourceLock::query()->where('task_id', $task->id)->delete();

            CreatePlanForTask::dispatch($task, revisionHints: "Step {$step->order} ({$step->specialist_role->label()}) failed: {$reason}");

            Log::info("Escalating to re-plan for task [{$task->id}] after step [{$step->id}] failure.");

            return;
        }

        $step->update(['status' => PlanStepStatus::Failed]);
        $plan->update(['status' => PlanStatus::Failed]);
        $task->update(['status' => AgentTaskStatus::Failed]);

        ResourceLock::query()->where('task_id', $task->id)->delete();

        TaskStatusUpdated::dispatch($task->load('conversation'), AgentTaskStatus::Failed, $reason);

        Log::error("Step [{$step->id}] halted — plan [{$plan->id}] failed: {$reason}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $e): void
    {
        $task = $this->task->fresh();
        $step = $this->step->fresh();

        if ($step && $task) {
            $this->failStep($step, $step->plan, $task, $e->getMessage());
        }

        Log::error("RunPlanStep failed for step [{$this->step->id}] on task [{$this->task->id}]: {$e->getMessage()}");
    }
}
