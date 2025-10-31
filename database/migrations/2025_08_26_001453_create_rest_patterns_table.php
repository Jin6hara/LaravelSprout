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
        Schema::create('rest_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('name');   // 例: 月火休日
            $table->string('code')->unique(); // 例: MON_TUE, THU_FRI
            $table->timestamps();
        });

        Schema::create('rest_pattern_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rest_pattern_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('weekday'); // 0(日)〜6(土)
            $table->enum('kind', ['work', 'prescribed_off', 'statutory_off']);
            $table->timestamps();
            $table->unique(['rest_pattern_id', 'weekday']);
        });

        Schema::create('user_rest_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rest_pattern_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');          // 例: 2025-04-01
            $table->date('end_date')->nullable(); // null = 以後ずっと
            $table->timestamps();
            $table->index(['user_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_rest_patterns');
        Schema::dropIfExists('rest_pattern_rules');
        Schema::dropIfExists('rest_patterns');
    }
};
