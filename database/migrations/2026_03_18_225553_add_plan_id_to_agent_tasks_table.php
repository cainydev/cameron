<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_tasks', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('goal_id')->constrained('agent_plans')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};
