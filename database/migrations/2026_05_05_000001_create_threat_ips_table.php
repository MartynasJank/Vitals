<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threat_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('country', 100)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('isp', 255)->nullable();
            $table->string('asn', 50)->nullable();
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_tor')->default(false);
            $table->unsignedInteger('total_hits')->default(1);
            $table->timestamp('first_seen')->useCurrent();
            $table->timestamp('last_seen')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threat_ips');
    }
};
