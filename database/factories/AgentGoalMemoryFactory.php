<?php

namespace Database\Factories;

use App\Models\AgentGoal;
use App\Models\AgentGoalMemory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentGoalMemory>
 */
class AgentGoalMemoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_goal_id' => AgentGoal::factory(),
            'insight' => fake()->sentence(),
            'expires_at' => now()->addHours(24),
        ];
    }
}
