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
        Schema::create('employment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');            // 入社日 / 再入社日 / 雇用形態変更開始日
            $table->date('end_date')->nullable();  // 終了日。NULL = 現在も継続
            $table->string('type_name');           // 雇用形態名
            $table->string('type_code');           // 雇用形態コード
            $table->string('note')->nullable();    // 備考
            $table->timestamps();

            // よく使う検索用のインデックス
            $table->index(['user_id', 'start_date']);
            $table->index(['user_id', 'end_date']);
            $table->index(['start_date', 'end_date']);
            $table->index(['user_id', 'type_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_terms');
    }
};
