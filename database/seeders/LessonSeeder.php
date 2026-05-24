<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('lessons')->upsert([
            ['id'=>1,'lesson_name'=>'Global',       'lesson_code'=>'BW', 'lesson_minute'=>45,'lesson_type'=>'kids',   'created_at'=>$now,'updated_at'=>$now],
            ['id'=>2,'lesson_name'=>'Free',         'lesson_code'=>'FTL','lesson_minute'=>40,'lesson_type'=>'Adults', 'created_at'=>$now,'updated_at'=>$now],
            ['id'=>3,'lesson_name'=>'Mini',         'lesson_code'=>'AK', 'lesson_minute'=>30,'lesson_type'=>'kids',   'created_at'=>$now,'updated_at'=>$now],
            ['id'=>4,'lesson_name'=>'Break',        'lesson_code'=>'Break','lesson_minute'=>45,'lesson_type'=>'Break','created_at'=>$now,'updated_at'=>$now],
            ['id'=>5,'lesson_name'=>'Envision',     'lesson_code'=>'EV', 'lesson_minute'=>40,'lesson_type'=>'Adults', 'created_at'=>$now,'updated_at'=>$now],
            ['id'=>6,'lesson_name'=>'Enjoy English','lesson_code'=>'EE', 'lesson_minute'=>40,'lesson_type'=>'Adults', 'created_at'=>$now,'updated_at'=>$now],
        ], ['id'], [
            'lesson_name',
            'lesson_code',
            'lesson_minute',
            'lesson_type',
            'updated_at',
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                select setval(
                    pg_get_serial_sequence('lessons', 'id'),
                    coalesce((select max(id) from lessons), 1),
                    (select count(*) > 0 from lessons)
                )
            ");
        }
    }
}
