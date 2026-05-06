<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subs', function (Blueprint $t) {
            $t->id();

            // FK：ユーザー削除を原則防ぐため restrictOnDelete（必要なら cascadeOnDelete に変更してOK）
            $t->foreignId('user_id')->constrained()->restrictOnDelete();

            $t->date('sub_date')->index();

            // 時間は未確定でも登録できるように nullable
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();

            $t->string('note')->nullable();

            // 合計分（分単位）。アプリ側で計算して保存する想定
            $t->unsignedInteger('total_duration')->default(0)->comment('minutes');

            $t->timestamps();

            // よく使う検索用に複合インデックス
            $t->index(['user_id', 'sub_date']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table "subs" add constraint "subs_total_duration_non_negative" check ("total_duration" >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subs');
    }
};
