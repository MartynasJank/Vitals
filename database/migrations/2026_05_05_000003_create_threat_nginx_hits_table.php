<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_threat')->create('nginx_hits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_id')->constrained('threat_ips')->cascadeOnDelete();
            $table->string('path', 2048)->nullable();
            $table->string('method', 10)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->enum('scan_type', ['env_probe', 'wp_admin', 'git_exposure', 'phpmyadmin', 'log4shell', 'spring_boot', 'other'])->default('other');
            $table->timestamp('timestamp')->useCurrent();

            $table->index('ip_id');
            $table->index('timestamp');
            $table->index('scan_type');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_threat')->dropIfExists('nginx_hits');
    }
};
