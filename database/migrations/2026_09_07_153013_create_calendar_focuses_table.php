<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_focuses', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();
            $table->text('focus_text');
            $table->string('review_body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_focuses');
    }
};
