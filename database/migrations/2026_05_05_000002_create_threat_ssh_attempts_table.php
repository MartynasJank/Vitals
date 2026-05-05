<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssh_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_id')->constrained('threat_ips')->cascadeOnDelete();
            $table->string('username', 255)->nullable();
            $table->timestamp('timestamp')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_attempts');
    }
};
