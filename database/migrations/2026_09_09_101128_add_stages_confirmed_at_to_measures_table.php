<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * task-020 — последовательное заполнение этапов
 * ([[Заполнение и утверждение#Последовательное заполнение этапов]]). `null` = список
 * этапов ещё наполняется (свободно редактируется); заполнено = зафиксирован, рабочее
 * место мероприятия переходит в последовательный режим (виден только текущий этап).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measures', function (Blueprint $table) {
            $table->timestamp('stages_confirmed_at')->nullable()->after('interim_monitoring_text');
        });
    }

    public function down(): void
    {
        Schema::table('measures', function (Blueprint $table) {
            $table->dropColumn('stages_confirmed_at');
        });
    }
};
