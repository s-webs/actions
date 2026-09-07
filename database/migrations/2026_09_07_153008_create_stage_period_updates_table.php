<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_period_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measure_stage_id')->constrained('measure_stages')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->text('done_text')->nullable();
            $table->text('next_step')->nullable();
            $table->date('next_step_date')->nullable();
            $table->string('review_state')->default('draft');
            $table->unsignedTinyInteger('approved_percent')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->string('submitted_via')->nullable();
            $table->string('submitted_by_name')->nullable();
            $table->string('submitted_session_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['measure_stage_id', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_period_updates');
    }
};
