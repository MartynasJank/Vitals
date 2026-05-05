<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threat_ips', function (Blueprint $table) {
            $table->string('asn', 255)->nullable()->change();
        });

        Schema::table('ssh_attempts', function (Blueprint $table) {
            $table->unique(['ip_id', 'timestamp'], 'unique_attempt');
        });
    }

    public function down(): void
    {
        Schema::table('ssh_attempts', function (Blueprint $table) {
            $table->dropUnique('unique_attempt');
        });

        Schema::table('threat_ips', function (Blueprint $table) {
            $table->string('asn', 50)->nullable()->change();
        });
    }
};
