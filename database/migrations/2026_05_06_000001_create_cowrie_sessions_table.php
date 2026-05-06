<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cowrie_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ip_id');
            $table->string('session', 32)->unique();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();

            $table->foreign('ip_id')->references('id')->on('threat_ips');
            $table->index('ip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cowrie_sessions');
    }
};
