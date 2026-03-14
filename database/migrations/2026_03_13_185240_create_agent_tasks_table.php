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
        Schema::create('agent_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained('agent_goals')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->json('context_payload')->default('{}');
            $table->string('locked_resource_id')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('locked_resource_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_tasks');
    }
};
