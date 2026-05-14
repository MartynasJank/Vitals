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
        Schema::table('nginx_hits', function (Blueprint $table) {
            $table->string('vhost', 253)->nullable()->after('ip_id');
        });
    }

    public function down(): void
    {
        Schema::table('nginx_hits', function (Blueprint $table) {
            $table->dropColumn('vhost');
        });
    }
};
