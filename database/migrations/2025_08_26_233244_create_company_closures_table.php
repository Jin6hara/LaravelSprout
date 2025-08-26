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
        Schema::create('company_closures', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // 表示名: ゴールデンウイーク, 夏休み, 冬休み 等
            $table->string('code')->index();        // GW / SB / WB など
            $table->date('start_date');
            $table->date('end_date');               // 例: 冬休みは年跨ぎOK（2025-12-31〜2026-01-05）
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['start_date','end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_closures');
    }
};
