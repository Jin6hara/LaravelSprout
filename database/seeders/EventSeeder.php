<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // user_id=8 : 10/15 (水) Umeda GB 13:30-21:15
        Event::create([
            'event_date'       => '2025-10-15',
            'school_name'      => 'Umeda GB',
            'start_time'       => '13:30',
            'end_time'         => '21:15',
            'sub'              => 'required',
            'assigned_user_id' => 8,
            'status'           => 'filled',
            'type'             => 'overtime',
        ]);

        // user_id=7 : 9/15 撮影（特別イベント）
        Event::create([
            'event_date'       => '2025-09-15',
            'title'            => '撮影',
            'school_name'      => 'Umeda GB',
            'start_time'       => '13:30',
            'end_time'         => '21:15',
            'sub'              => 'required',
            'assigned_user_id' => 7,
            'status'           => 'filled',
            'type'             => 'overtime',
        ]);

        // user_id=7 : 10/17 (金) Umeda GB 13:30-21:15（残業）
        Event::create([
            'event_date'       => '2025-10-17',
            'school_name'      => 'Umeda GB',
            'start_time'       => '13:30',
            'end_time'         => '21:15',
            'sub'              => 'required',
            'assigned_user_id' => 7,
            'status'           => 'filled',
            'type'             => 'overtime',
        ]);

        // user_id=7 : 10/19 (日) Ikoma 10:00-17:45（Sub代行）
        Event::create([
            'event_date'       => '2025-10-19',
            'school_name'      => 'Ikoma',
            'start_time'       => '10:00',
            'end_time'         => '17:45',
            'sub'              => 'required',
            'assigned_user_id' => 7,
            'status'           => 'filled',
            'type'             => 'overtime',
        ]);

        // user_id=5 : 10/15 (水) Ikoma 14:00-21:45
        Event::create([
            'event_date'       => '2025-10-15',
            'school_name'      => 'Ikoma',
            'start_time'       => '14:00',
            'end_time'         => '21:45',
            'sub'              => 'required',
            'assigned_user_id' => 5,
            'status'           => 'filled',
            'type'             => 'overtime',
        ]);
        // user_id=5 : 10/16 (木) Tennoji MP 14:00-21:45
        Event::create([
            'event_date'       => '2025-10-16',
            'school_name'      => 'Tennoji MP',
            'start_time'       => '14:00',
            'end_time'         => '21:45',
            'sub'              => 'required',
            'assigned_user_id' => 5,
            'status'           => 'filled',
            'type'             => 'overtime',
        ]);
        // user_id=5 : 10/18 (土) Yao 10:00-14:00（残業）
        Event::create([
            'event_date'       => '2025-10-18',
            'school_name'      => 'Yao',
            'start_time'       => '10:00',
            'end_time'         => '14:00',
            'sub'              => 'required',
            'assigned_user_id' => 5,
            'status'           => 'filled',
            'type'             => 'overtime',
        ]);
        // user_id=5 : 10/18 (土) Fujitera 15:00-17:45（残業）※同日2コマOK・重なり無し
        Event::create([
            'event_date'       => '2025-10-18',
            'school_name'      => 'Fujitera',
            'start_time'       => '15:00',
            'end_time'         => '17:45',
            'sub'              => 'required',
            'assigned_user_id' => 5,
            'status'           => 'filled',
            'type'             => 'overtime',
        ]);
    }
}
