<?php

namespace Database\Factories;

use App\Enums\AgentRole;
use App\Enums\PlanStepStatus;
use App\Models\AgentPlan;
use App\Models\AgentPlanStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentPlanStep>
 */
class AgentPlanStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => AgentPlan::factory(),
            'order' => 1,
            'specialist_role' => fake()->randomElement(AgentRole::cases()),
            'action' => fake()->sentence(),
            'depends_on_step_id' => null,
            'status' => PlanStepStatus::Pending,
            'on_failure' => 'retry',
            'output_summary' => null,
            'conversation_id' => null,
            'retry_count' => 0,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Set the specialist role.
     */
    public function forRole(AgentRole $role): static
    {
        return $this->state(fn () => ['specialist_role' => $role]);
    }

    /**
     * Indicate the step is running.
     */
    public function running(): static
    {
        return $this->state(fn () => [
            'status' => PlanStepStatus::Running,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate the step is completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => PlanStepStatus::Completed,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
            'output_summary' => fake()->paragraph(),
        ]);
    }

    /**
     * Indicate the step has failed.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PlanStepStatus::Failed,
            'started_at' => now()->subMinutes(2),
        ]);
    }

    /**
     * Set the failure strategy to escalate.
     */
    public function escalateOnFailure(): static
    {
        return $this->state(fn () => ['on_failure' => 'escalate']);
    }

    /**
     * Set the failure strategy to halt.
     */
    public function haltOnFailure(): static
    {
        return $this->state(fn () => ['on_failure' => 'halt']);
    }
}
