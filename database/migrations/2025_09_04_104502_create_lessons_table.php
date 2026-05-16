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
    public function up(): void {
        Schema::create('lessons', function (Blueprint $t) {
            $t->id();
            $t->string('lesson_name')->nullable();
            $t->string('lesson_code')->nullable()->index();
            $t->string('note')->nullable();
            $t->unsignedSmallInteger('lesson_minute')->nullable(); // 30/40/45など
            $t->string('lesson_type')->nullable();
            $t->string('ps_unique_lesson_code')->nullable()->index(); // CSV import参照用
            $t->string('fm_lesson_code')->nullable()->index();        // CSV import参照用
            $t->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table "lessons" add constraint "lessons_lesson_minute_non_negative" check ("lesson_minute" >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
