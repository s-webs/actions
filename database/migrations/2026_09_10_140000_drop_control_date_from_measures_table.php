<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measures', function (Blueprint $table) {
            $table->dropColumn('control_date');
        });
    }

    public function down(): void
    {
        Schema::table('measures', function (Blueprint $table) {
            $table->date('control_date')->nullable()->after('interim_monitoring_text');
        });
    }
};
