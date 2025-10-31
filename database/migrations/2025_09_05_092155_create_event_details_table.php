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
        Schema::create('event_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            // 元ネタ参照（無くなってもイベントは生きるように null 許容＋on delete set null）
            $t->foreignId('schedule_detail_id')->nullable()
                ->constrained('schedule_details')->nullOnDelete();

            // スナップショット（冗長だが安全・速い）
            $t->foreignId('lesson_start_time_id')->constrained('lesson_start_times')->cascadeOnDelete();
            $t->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();

            $t->unique(['event_id', 'lesson_start_time_id', 'lesson_id'], 'event_details_unique');
            $t->index(['event_id', 'lesson_start_time_id'], 'event_details_event_start_idx');
            // timestamps不要
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_details');
    }
};
