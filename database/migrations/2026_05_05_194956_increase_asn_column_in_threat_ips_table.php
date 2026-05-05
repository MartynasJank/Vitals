<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threat_ips', function (Blueprint $table) {
            $table->string('asn', 255)->nullable()->change();
        });

        DB::statement('
            DELETE s1 FROM ssh_attempts s1
            INNER JOIN ssh_attempts s2
            WHERE s1.id > s2.id AND s1.ip_id = s2.ip_id AND s1.timestamp = s2.timestamp
        ');

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
