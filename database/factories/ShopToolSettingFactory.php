<?php

namespace Database\Factories;

use App\Enums\ToolCategory;
use App\Models\Shop;
use App\Models\ShopToolSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopToolSetting>
 */
class ShopToolSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'category' => fake()->randomElement(ToolCategory::cases()),
            'is_enabled' => true,
            'approval_mode' => 'default',
            'tool_overrides' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }

    public function autoApprove(): static
    {
        return $this->state(['approval_mode' => 'auto']);
    }

    public function requireApproval(): static
    {
        return $this->state(['approval_mode' => 'require_approval']);
    }
}
