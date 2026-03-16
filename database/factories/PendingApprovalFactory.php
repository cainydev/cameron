<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Models\AgentTask;
use App\Models\PendingApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PendingApproval>
 */
class PendingApprovalFactory extends Factory
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
            'tool_class' => 'App\\Ai\\Tools\\UpdateAdsCampaignStatus',
            'payload' => ['campaign_id' => fake()->randomNumber(5)],
            'reasoning' => fake()->sentence(),
            'expires_at' => now()->addHours(24),
            'status' => ApprovalStatus::Waiting,
        ];
    }

    /**
     * Indicate the approval has been approved.
     */
    public function approved(): static
    {
        return $this->state(fn () => ['status' => ApprovalStatus::Approved]);
    }

    /**
     * Indicate the approval has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ApprovalStatus::Rejected]);
    }
}
