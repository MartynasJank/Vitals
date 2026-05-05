<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threat_ips', function (Blueprint $table) {
            $table->decimal('lat', 8, 5)->nullable()->after('asn');
            $table->decimal('lon', 8, 5)->nullable()->after('lat');
            $table->string('org', 255)->nullable()->after('lon');
        });

        Schema::table('nginx_hits', function (Blueprint $table) {
            $table->string('referer', 512)->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('threat_ips', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lon', 'org']);
        });

        Schema::table('nginx_hits', function (Blueprint $table) {
            $table->dropColumn('referer');
        });
    }
};
