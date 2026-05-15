<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_line_id')->constrained('schedule_lines')->cascadeOnDelete();
            $t->time('start_time');
            $t->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();

            // ▼ 有効期間
            $t->date('effective_start');              // 必須
            $t->date('effective_end')->nullable();    // NULL=オープンエンド
            $t->text('note')->nullable();             // 備考

            $t->timestamps();

            // ▼ 期間検索のための複合インデックス（範囲→行絞り込みの順で効きやすい並び）
            $t->index(
                ['schedule_line_id', 'effective_start', 'effective_end'],
                'sch_details_line_window_idx'
            );
            // 「当日表示」最適化（行→開始時刻→期間）
            $t->index(
                ['schedule_line_id', 'start_time', 'effective_start', 'effective_end'],
                'sch_details_line_start_window_idx'
            );
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
