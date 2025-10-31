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
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->date('event_date')->index();
            $t->foreignId('original_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('Leave_type')->nullable();
            $t->string('title')->nullable();        // 例: 撮影、OPPT/ML
            $t->string('school_name')->nullable();
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->string('total_duration', 5)->nullable(); // "H:MM" 最大 "23:59" を想定
            $t->text('Lesson')->nullable();
            $t->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->enum('status', ['pending', 'fixed', 'filled', 'in_process'])->default('pending')->index();
            $t->enum('type', ['regular_time', 'none_required', 'overtime', 'schedule_change', 'rostered_working_day', 'special'])->index();
            $t->text('notes')->nullable();
            // 欠席時コピー元（正規コマ追跡用）
            $t->foreignId('source_schedule_line_id')->nullable()->constrained('schedule_lines')->nullOnDelete();
            // どのLeaveから生成されたスナップショットか（※CREATEでは after() を使わない）
            $t->foreignId('source_leave_id')->nullable()->constrained('leaves')->nullOnDelete();
            $t->timestamps();
            // 重複防止の最低限（完全な“時間かぶり”はアプリ層で検証）
            $t->index(['assigned_user_id', 'event_date', 'start_time', 'end_time'], 'events_user_date_time_idx');
            $t->index(['source_leave_id', 'event_date'], 'events_leave_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
