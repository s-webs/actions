<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measure_period_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measure_id')->constrained('measures')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->string('status')->default('not_started');
            $table->text('risk_text')->nullable();
            $table->boolean('needs_decision')->default(false);
            $table->timestamps();
            $table->unique(['measure_id', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measure_period_states');
    }
};
