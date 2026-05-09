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
        Schema::create('schedule_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('parent_line_id')->nullable()->constrained('schedule_lines')->nullOnDelete();
            $t->unsignedInteger('total_minutes')->default(0);
            $t->tinyInteger('dow');                        // 0(日)〜6(土)
            $t->string('school_name');
            $t->time('start_time');
            $t->time('end_time');
            $t->date('effective_start');
            $t->date('effective_end');
            $t->text('handover_memo')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'dow', 'effective_start', 'effective_end'], 'sch_line_idx');
            $t->index(['school_name', 'effective_start', 'effective_end'], 'sch_line_school_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_lines');
    }
};
