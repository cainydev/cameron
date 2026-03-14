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
        Schema::table('agent_goals', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('is_active');
            $table->boolean('is_one_off')->default(false)->after('expires_at');
            $table->timestamp('completed_at')->nullable()->after('is_one_off');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_goals', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'is_one_off', 'completed_at']);
        });
    }
};
