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
            $table->unsignedSmallInteger('check_frequency_minutes')->default(60)->after('is_one_off');
            $table->timestamp('last_checked_at')->nullable()->after('check_frequency_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_goals', function (Blueprint $table) {
            $table->dropColumn(['check_frequency_minutes', 'last_checked_at']);
        });
    }
};
