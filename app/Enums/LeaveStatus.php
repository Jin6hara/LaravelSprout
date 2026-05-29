<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LeaveStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Archived => 'Archived',
        };
    }

    public static function manageLabels(): array
    {
        return [
            self::Approved->value => self::Approved->label(),
            self::Pending->value => self::Pending->label(),
            self::Rejected->value => self::Rejected->label(),
            self::Cancelled->value => self::Cancelled->label(),
        ];
    }

    public static function storeValues(): array
    {
        return [
            self::Approved->value,
            self::Pending->value,
            self::Rejected->value,
        ];
    }

    public static function snapshotValues(): array
    {
        return [
            self::Approved->value,
            self::Pending->value,
        ];
    }
}
