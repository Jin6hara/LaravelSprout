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
            $table->date('start_date');            // 入社日 or 再入社日（将来日なら入社待ち）
            $table->date('end_date')->nullable();  // 退職日（未定ならNULL = 無期限）
            $table->string('note')->nullable();    // 備考（契約種別などもここでOK）
            $table->timestamps();

            // よく使う検索用のインデックス
            $table->index(['user_id', 'start_date']);
            $table->index(['user_id', 'end_date']);
            $table->index(['start_date', 'end_date']);
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
