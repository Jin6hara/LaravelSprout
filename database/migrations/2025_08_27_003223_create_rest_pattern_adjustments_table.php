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
        Schema::create('rest_pattern_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rest_pattern_id')->constrained()->cascadeOnDelete();
            $table->date('date'); // 調整対象日（年度を跨いでもOK）
            $table->enum('kind', ['add_off', 'work_instead']); // add_off=ORD, work_instead=RWD
            $table->string('code')->default('');  // 'ORD' / 'RWD' など
            $table->string('title')->nullable();  // 表示名を上書きしたい場合
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['rest_pattern_id', 'date']); // 同一日に二重定義を防止
            $table->index(['date', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rest_pattern_adjustments');
    }
};
