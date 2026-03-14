<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'url' => fake()->url(),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'ga4_property_id' => '123456789',
            'google_ads_customer_id' => '1234567890',
            'search_console_url' => 'https://example.com',
            'base_instructions' => null,
            'brand_guidelines' => null,
            'target_roas' => null,
        ];
    }
}
