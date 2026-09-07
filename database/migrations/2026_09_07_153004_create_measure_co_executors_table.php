<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measure_co_executors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measure_id')->constrained('measures')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measure_co_executors');
    }
};
