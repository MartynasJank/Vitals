<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cowrie_downloads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cowrie_session_id');
            $table->text('url');
            $table->string('filename', 255)->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->timestamp('timestamp')->useCurrent();

            $table->foreign('cowrie_session_id')->references('id')->on('cowrie_sessions')->onDelete('cascade');
            $table->index('cowrie_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cowrie_downloads');
    }
};
