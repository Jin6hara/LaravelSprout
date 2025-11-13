<?php

// 廃棄(protected $casts = ['type' => ShiftType::class,]はちょっと危ない)。
// getTypeLabelAttribute()で対応する。

namespace App\Enums;

enum ShiftType: string
{
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
}
