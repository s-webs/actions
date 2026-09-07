<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measures', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->foreignId('direction_id')->constrained('directions');
            $table->text('title');
            $table->foreignId('responsible_id')->nullable()->constrained('responsibles')->nullOnDelete();
            $table->date('deadline')->nullable();
            $table->text('interim_monitoring_text')->nullable();
            $table->date('control_date')->nullable();
            $table->string('completion_form')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('risk_level')->nullable();
            $table->unsignedTinyInteger('percent')->default(0);
            $table->text('proctor_comment')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measures');
    }
};
