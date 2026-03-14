<?php

namespace Database\Factories;

use App\Enums\AgentTaskStatus;
use App\Models\AgentGoal;
use App\Models\AgentTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentTask>
 */
class AgentTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => AgentGoal::factory(),
            'status' => AgentTaskStatus::Pending,
            'context_payload' => [],
            'locked_resource_id' => null,
            'conversation_id' => null,
        ];
    }

    /**
     * Indicate the task is running.
     */
    public function running(): static
    {
        return $this->state(fn () => ['status' => AgentTaskStatus::Running]);
    }

    /**
     * Indicate the task is waiting for approval.
     */
    public function waitingApproval(): static
    {
        return $this->state(fn () => ['status' => AgentTaskStatus::WaitingApproval]);
    }

    /**
     * Indicate the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => ['status' => AgentTaskStatus::Completed]);
    }

    /**
     * Indicate the task is aborted.
     */
    public function aborted(): static
    {
        return $this->state(fn () => ['status' => AgentTaskStatus::Aborted]);
    }

    /**
     * Indicate the task has failed.
     */
    public function failed(): static
    {
        return $this->state(fn () => ['status' => AgentTaskStatus::Failed]);
    }
}
