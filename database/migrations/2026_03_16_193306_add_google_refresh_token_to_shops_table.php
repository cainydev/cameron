<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->text('google_refresh_token')->nullable()->after('search_console_url');
            $table->string('merchant_center_id')->nullable()->after('google_refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['google_refresh_token', 'merchant_center_id']);
        });
    }
};
