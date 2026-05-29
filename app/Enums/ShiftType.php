<?php

// 廃棄(protected $casts = ['type' => ShiftType::class,]はちょっと危ない)。
// getTypeLabelAttribute()で対応する。

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ShiftType: string
{
    use HasValues;

    case RegularTime        = 'regular_time';
    case Overtime           = 'overtime';
    case ScheduleChange     = 'schedule_change';
    case Special            = 'special';
    case RosteredWorkingDay = 'rostered_working_day';
    case NoneRequired       = 'none_required';

    public function short(): string
    {
        return match ($this) {
            self::RegularTime        => 'RT',
            self::Overtime           => 'OT',
            self::ScheduleChange     => 'SC',
            self::Special            => 'SP',
            self::RosteredWorkingDay => 'RWD',
            self::NoneRequired       => 'NS',
        };
    }

    public function calendarLabel(): string
    {
        return match ($this) {
            self::Overtime => '残業',
            self::RegularTime => '通常勤務',
            self::Special => '特別イベント',
            self::ScheduleChange => '時間変更',
            self::RosteredWorkingDay => '振替出勤',
            self::NoneRequired => '勤務不要',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Overtime => 10,
            self::RegularTime => 20,
            self::Special => 30,
            self::ScheduleChange => 40,
            self::RosteredWorkingDay => 45,
            self::NoneRequired => 50,
        };
    }
}
