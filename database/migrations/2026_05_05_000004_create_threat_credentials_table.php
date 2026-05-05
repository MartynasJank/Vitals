<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema->create('credentials', function (Blueprint $table) {
            $table->id();
            $table->string('username', 255);
            $table->string('password', 255);
            $table->unsignedInteger('hit_count')->default(1);
            $table->timestamp('first_seen')->useCurrent();
            $table->timestamp('last_seen')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['username', 'password'], 'unique_pair');
        });
    }

    public function down(): void
    {
        Schema->dropIfExists('credentials');
    }
};
