<?php

namespace Database\Seeders;

use App\Models\AgentGoal;
use Illuminate\Database\Seeder;

class AgentGoalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AgentGoal::factory()->count(5)->create();
    }
}
