<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('lessons')->insert([
            ['id'=>1,'lesson_name'=>'Global',       'lesson_code'=>'BW', 'lesson_minute'=>45,'lesson_type'=>'kids'],
            ['id'=>2,'lesson_name'=>'Free',         'lesson_code'=>'FTL','lesson_minute'=>40,'lesson_type'=>'Adults'],
            ['id'=>3,'lesson_name'=>'Mini',         'lesson_code'=>'AK', 'lesson_minute'=>30,'lesson_type'=>'kids'],
            ['id'=>4,'lesson_name'=>'Break',        'lesson_code'=>'Break','lesson_minute'=>45,'lesson_type'=>'Break'],
            ['id'=>5,'lesson_name'=>'Envision',     'lesson_code'=>'EV', 'lesson_minute'=>40,'lesson_type'=>'Adults'],
            ['id'=>6,'lesson_name'=>'Enjoy English','lesson_code'=>'EE', 'lesson_minute'=>40,'lesson_type'=>'Adults'],
        ]);
    }
}
