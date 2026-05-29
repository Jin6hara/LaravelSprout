<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LeaveKind: string
{
    use HasValues;

    case Paid = 'paid';
    case AbsenceToPaid = 'absence_to_paid';
    case Special = 'special';
    case Absence = 'absence';
    case Adjustment = 'adjustment';
    case LeftEarly = 'left_early';
    case Late = 'late';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'ALP',
            self::AbsenceToPaid => 'MT to ALP',
            self::Special => 'Special',
            self::Absence => 'MT',
            self::Adjustment => 'Adjustment',
            self::LeftEarly => 'Left Early',
            self::Late => 'Late',
            self::Other => 'Other',
        };
    }

    public function absenceReportLabel(): string
    {
        return match ($this) {
            self::Absence => 'Unpaid Leave',
            self::AbsenceToPaid => 'ALP',
            self::Other => 'Others',
            default => $this->label(),
        };
    }

    public function calendarTitle(?string $specialType = null): string
    {
        return match ($this) {
            self::Paid, self::AbsenceToPaid => 'ALP',
            self::Special => $specialType ? "Special Leave（{$specialType}）" : 'Special Leave',
            self::Absence => 'Absence',
            self::Late => 'Late',
            self::LeftEarly => 'Left Early',
            self::Adjustment => 'Adjustment',
            self::Other => $specialType ?: 'Other',
        };
    }

    public static function storeValues(): array
    {
        return [
            self::Paid->value,
            self::Special->value,
            self::Absence->value,
            self::Late->value,
            self::Other->value,
        ];
    }

    public static function applicationValues(): array
    {
        return [
            self::Paid->value,
            self::Special->value,
        ];
    }

    public static function labels(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $labels, self $case) => $labels + [$case->value => $case->label()],
            []
        );
    }

    public static function absenceReportValues(): array
    {
        return [
            self::Absence->value,
            self::AbsenceToPaid->value,
            self::Other->value,
        ];
    }

    public static function absenceReportLabels(): array
    {
        return array_reduce(
            [self::Absence, self::AbsenceToPaid, self::Other],
            fn (array $labels, self $case) => $labels + [$case->value => $case->absenceReportLabel()],
            []
        );
    }
}
