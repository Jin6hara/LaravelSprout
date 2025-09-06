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
        Schema::create('schedule_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_line_id')->constrained('schedule_lines')->cascadeOnDelete();
            $t->foreignId('lesson_start_time_id')->constrained('lesson_start_times')->cascadeOnDelete();
            $t->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();

            // 重複防止
            $t->unique(['schedule_line_id','lesson_start_time_id','lesson_id'], 'sch_details_unique');

            // 検索最適化
            $t->index(['schedule_line_id','lesson_start_time_id'], 'sch_details_line_start_idx');

            // timestamps不要
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_details');
    }
};
