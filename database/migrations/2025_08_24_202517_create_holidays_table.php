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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');                 // 例: 元日, 春分の日
            $table->string('name_en')->nullable();  // 任意: 英語名
            $table->string('country_code', 2)->default('JP');
            $table->unsignedSmallInteger('year');
            $table->boolean('is_observed')->default(false); // 振替休日・国民の休日か
            $table->string('source')->nullable();   // 出典URL
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
