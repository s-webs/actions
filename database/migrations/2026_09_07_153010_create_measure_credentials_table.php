<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measure_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measure_id')->unique()->constrained('measures')->cascadeOnDelete();
            $table->string('login')->unique();
            $table->string('password_hash');
            $table->timestamp('rotated_at')->nullable();
            $table->foreignId('rotated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measure_credentials');
    }
};
