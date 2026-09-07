<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Этап 2 — «Рабочая группа: решения и поручения» ([[Функциональные требования#4.8]]).
 * Заглушка: таблица создаётся сейчас вместе с остальной моделью данных, бизнес-логика
 * (task-008 в бэклоге Этапа 2) не реализуется в рамках MVP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->date('held_at');
            $table->string('type');
            $table->text('agenda')->nullable();
            $table->string('protocol_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
