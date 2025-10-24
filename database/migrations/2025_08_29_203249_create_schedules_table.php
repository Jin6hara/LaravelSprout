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
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('label')->nullable();               // 表示用 (例: "James weekly")
            $t->unsignedInteger('total_minutes')->default(0);
            $t->date('effective_start');
            $t->date('effective_end');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['user_id', 'effective_start', 'effective_end'], 'sch_user_period_idx');
        });

        // 2) schedule_lines
        Schema::create('schedule_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('parent_line_id')->nullable()->constrained('schedule_lines')->nullOnDelete();
            $t->tinyInteger('dow');                        // 0(日)〜6(土)
            $t->string('school_name');
            $t->time('start_time');
            $t->time('end_time');
            $t->date('effective_start');
            $t->date('effective_end');
            $t->text('handover_memo')->nullable();
            $t->timestamps();
            $t->index(
                ['schedule_id', 'dow', 'effective_start', 'effective_end'],
                'sch_line_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('schedule_lines');
    }
};
