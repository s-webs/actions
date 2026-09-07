<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measure_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measure_id')->constrained('measures')->cascadeOnDelete();
            $table->unsignedSmallInteger('order');
            $table->string('title');
            $table->date('planned_date')->nullable();
            $table->unsignedTinyInteger('weight')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measure_stages');
    }
};
