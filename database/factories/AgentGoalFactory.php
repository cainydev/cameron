<?php

namespace Database\Factories;

use App\Models\AgentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentGoal>
 */
class AgentGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'sensor_tool_class' => 'App\\Ai\\Tools\\'.fake()->word().'Sensor',
            'sensor_arguments' => [],
            'conditions' => [
                ['metric' => 'roas', 'operator' => '>=', 'value' => 3.0],
            ],
            'is_active' => true,
            'expires_at' => null,
            'is_one_off' => false,
            'check_frequency_minutes' => 60,
            'last_checked_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate the goal is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Indicate the goal is temporal with a deadline.
     */
    public function temporal(\DateTimeInterface|string|null $expiresAt = null): static
    {
        return $this->state(fn () => [
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);
    }

    /**
     * Indicate the goal has already expired.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Indicate the goal is a one-off milestone.
     */
    public function oneOff(): static
    {
        return $this->state(fn () => ['is_one_off' => true]);
    }

    /**
     * Indicate the goal has been completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'completed_at' => now(),
        ]);
    }
}
