<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measure_id')->constrained('measures')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->unsignedTinyInteger('percent');
            $table->string('status');
            $table->string('risk_level')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->unique(['measure_id', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');
    }
};
