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
        Schema::table('shops', function (Blueprint $table) {
            $table->string('url')->nullable()->after('name');
            $table->string('timezone')->default('UTC')->after('url');
            $table->string('currency', 3)->default('USD')->after('timezone');
            $table->text('base_instructions')->nullable()->after('search_console_url');
            $table->text('brand_guidelines')->nullable()->after('base_instructions');
            $table->string('target_roas')->nullable()->after('brand_guidelines');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['url', 'timezone', 'currency', 'base_instructions', 'brand_guidelines', 'target_roas']);
        });
    }
};
