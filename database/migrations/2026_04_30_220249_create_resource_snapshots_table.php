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
        Schema::create('resource_snapshots', function (Blueprint $table) {
            $table->id();
            $table->float('cpu_percent');
            $table->integer('ram_used_mb');
            $table->integer('ram_total_mb');
            $table->float('disk_used_gb');
            $table->float('disk_total_gb');
            $table->timestamp('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_snapshots');
    }
};
