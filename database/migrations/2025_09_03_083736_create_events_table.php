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

            // 独立イベント：単日（AllDay も許容）
            $t->date('event_date')->index();

            // 表示＆連携用
            $t->string('title')->nullable();        // 例: 撮影
            $t->string('school_name')->nullable();  // 例: Umeda GB / Ikoma / ...
            $t->time('start_time')->nullable();     // nullならAllDay
            $t->time('end_time')->nullable();

            // 属性：event.on の種別
            // regular_copy=欠席発生時にregularのコマをコピー
            // overtime=残業, sub=代行, special=特別イベント, other=その他
            $t->enum('kind', ['regular_copy', 'overtime', 'sub', 'special', 'other'])->index();

            // 誰が入るか（担当者は単一前提。将来複数ならpivot追加）
            $t->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            // 元々の担当（代行トレース用）
            $t->foreignId('original_user_id')->nullable()->constrained('users')->nullOnDelete();

            // 欠席時コピー元（正規コマ追跡用）
            $t->foreignId('source_schedule_line_id')->nullable()->constrained('schedule_lines')->nullOnDelete();

            // 運用補助
            $t->enum('status', ['confirmed', 'draft', 'cancelled'])->default('confirmed')->index();
            $t->text('notes')->nullable();

            $t->timestamps();

            // 重複防止の最低限（完全な“時間かぶり”はアプリ層で検証）
            $t->index(['assigned_user_id', 'event_date', 'start_time', 'end_time'], 'events_user_date_time_idx');
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
