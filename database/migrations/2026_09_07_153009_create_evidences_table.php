<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measure_id')->constrained('measures')->cascadeOnDelete();
            $table->foreignId('measure_stage_id')->nullable()->constrained('measure_stages')->nullOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->string('type');
            $table->string('path_or_url');
            $table->string('title')->nullable();
            $table->string('form')->nullable();
            $table->string('uploaded_via')->nullable();
            $table->string('uploaded_by_name')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
    }
};
