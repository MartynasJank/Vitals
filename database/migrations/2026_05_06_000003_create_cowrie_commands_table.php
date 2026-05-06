<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cowrie_commands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cowrie_session_id');
            $table->text('input');
            $table->timestamp('timestamp')->useCurrent();

            $table->foreign('cowrie_session_id')->references('id')->on('cowrie_sessions')->onDelete('cascade');
            $table->index('cowrie_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cowrie_commands');
    }
};
