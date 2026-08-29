<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reminder_id')->constrained('reminders')->onDelete('cascade');
            $table->enum('canal', ['email', 'sms', 'push']);
            $table->dateTime('fecha_envio');
            $table->enum('estado', ['enviado', 'pendiente', 'fallido'])->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
