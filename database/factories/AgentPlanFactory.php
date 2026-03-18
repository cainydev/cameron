<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\AgentGoal;
use App\Models\AgentPlan;
use App\Models\AgentTask;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentPlan>
 */
class AgentPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => AgentTask::factory(),
            'goal_id' => AgentGoal::factory(),
            'shop_id' => Shop::factory(),
            'status' => PlanStatus::Planning,
            'objective' => fake()->sentence(),
            'working_memory' => [],
            'conversation_id' => null,
            'retry_count' => 0,
        ];
    }

    /**
     * Indicate the plan is executing.
     */
    public function executing(): static
    {
        return $this->state(fn () => ['status' => PlanStatus::Executing]);
    }

    /**
     * Indicate the plan is waiting for approval.
     */
    public function waitingApproval(): static
    {
        return $this->state(fn () => ['status' => PlanStatus::WaitingApproval]);
    }

    /**
     * Indicate the plan is completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => ['status' => PlanStatus::Completed]);
    }

    /**
     * Indicate the plan has failed.
     */
    public function failed(): static
    {
        return $this->state(fn () => ['status' => PlanStatus::Failed]);
    }
}
