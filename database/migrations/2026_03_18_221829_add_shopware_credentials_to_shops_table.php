<?php

declare(strict_types=1);

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
            $table->string('shopware_url')->nullable()->after('url');
            $table->string('shopware_client_id')->nullable()->after('shopware_url');
            $table->text('shopware_client_secret')->nullable()->after('shopware_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['shopware_url', 'shopware_client_id', 'shopware_client_secret']);
        });
    }
};
