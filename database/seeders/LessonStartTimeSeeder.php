<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LessonStartTimeSeeder extends Seeder
{
    public function run(): void
    {
        // 9:00 ～ 22:55 の 5分刻み（合計 168）
        $t = Carbon::createFromTime(9, 0, 0);
        $end = Carbon::createFromTime(22, 55, 0);

        $rows = [];
        while ($t->lte($end)) {
            $rows[] = ['start_time' => $t->format('H:i:s')];
            $t->addMinutes(5);
        }

        DB::table('lesson_start_times')->insert($rows);
    }
}
