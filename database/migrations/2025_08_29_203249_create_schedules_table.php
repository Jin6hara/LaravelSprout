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
        // 1) schedules
        Schema::create('schedules', function (Blueprint $t) {
            $t->id();
            $t->string('label')->nullable();               // 表示用 (例: "James weekly")
            $t->unsignedInteger('total_minutes')->default(0);
            $t->date('effective_start');
            $t->date('effective_end');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['effective_start', 'effective_end']);
        });

        // 2) schedule_lines
        Schema::create('schedule_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $t->tinyInteger('dow');                        // 0(日)〜6(土)
            $t->string('school_name');
            $t->time('start_time');
            $t->time('end_time');
            $t->date('effective_start');
            $t->date('effective_end');
            $t->timestamps();
            $t->index(
                ['schedule_id', 'dow', 'effective_start', 'effective_end'],
                'sch_line_idx'
            );
            // DBレベルのチェックは環境により難しいため、重複検証はアプリ/Seeder側で
        });

        // 3) user_schedule_assignments
        Schema::create('user_schedule_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $t->date('start_date');
            $t->date('end_date');
            $t->timestamps();
            $t->index(['user_id', 'start_date', 'end_date']);
            $t->unique(['user_id', 'schedule_id', 'start_date']); // お好みで
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('schedule_lines');
        Schema::dropIfExists('user_schedule_assignments');
    }
};
