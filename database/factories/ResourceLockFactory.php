<?php

namespace Database\Factories;

use App\Models\AgentTask;
use App\Models\ResourceLock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceLock>
 */
class ResourceLockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource_id' => 'GoogleAdCampaign:'.fake()->unique()->randomNumber(5),
            'task_id' => AgentTask::factory(),
            'expires_at' => now()->addHour(),
        ];
    }

    /**
     * Indicate the lock has expired.
     */
    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }
}
