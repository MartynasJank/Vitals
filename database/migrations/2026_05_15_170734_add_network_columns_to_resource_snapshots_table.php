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
        Schema::table('resource_snapshots', function (Blueprint $table) {
            $table->float('rx_rate_kbps')->nullable()->after('disk_total_gb');
            $table->float('tx_rate_kbps')->nullable()->after('rx_rate_kbps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resource_snapshots', function (Blueprint $table) {
            $table->dropColumn(['rx_rate_kbps', 'tx_rate_kbps']);
        });
    }
};
