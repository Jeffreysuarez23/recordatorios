<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reminder_id')->constrained('reminders')->onDelete('cascade');
            $table->integer('anticipacion_minutos');
            $table->enum('canal', ['email', 'sms', 'push'])->default('email');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_settings');
    }
};
