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
        Schema::table('cowrie_logins', function (Blueprint $table) {
            $table->boolean('is_success')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('cowrie_logins', function (Blueprint $table) {
            $table->dropColumn('is_success');
        });
    }
};
