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
        Schema::create('agent_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained('agent_tasks')->nullOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained('agent_goals')->nullOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('status')->default('planning');
            $table->text('objective');
            $table->json('working_memory')->default('{}');
            $table->string('conversation_id', 36)->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_plans');
    }
};
