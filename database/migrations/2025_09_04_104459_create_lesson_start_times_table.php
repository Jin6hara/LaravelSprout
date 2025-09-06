<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('lesson_start_times', function (Blueprint $t) {
            $t->id();
            $t->time('start_time'); // 例: 09:00, 09:05, ...
            $t->unique('start_time');
            // timestamps不要
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_start_times');
    }
};
