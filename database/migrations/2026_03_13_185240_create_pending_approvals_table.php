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
        Schema::create('pending_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('agent_tasks')->cascadeOnDelete();
            $table->string('tool_class');
            $table->json('payload');
            $table->text('reasoning');
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('waiting');
            $table->timestamps();

            $table->index('status');
            $table->index('task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_approvals');
    }
};
