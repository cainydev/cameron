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
        Schema::create('agent_plan_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('agent_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('order');
            $table->string('specialist_role');
            $table->text('action');
            $table->foreignId('depends_on_step_id')->nullable()->constrained('agent_plan_steps')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('on_failure')->default('retry');
            $table->text('output_summary')->nullable();
            $table->string('conversation_id', 36)->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'order']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_plan_steps');
    }
};
