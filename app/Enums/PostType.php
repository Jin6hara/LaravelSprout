<?php

namespace App\Enums;

enum PostType: string
{
    case Announcement  = 'announcement';
    case Inquiry       = 'inquiry';
    case DirectMessage = 'dm';
    case NoticeUrgent  = 'notice_urgent';
    case Maintenance   = 'maintenance';
    case Other         = 'other';

    // タイプ別の既定値（UI/保存時の既定）
    public function defaultAllowReplies(): bool
    {
        return match ($this) {
            self::Announcement, self::Maintenance => false,
            self::Inquiry, self::DirectMessage, self::NoticeUrgent, self::Other => true,
        };
    }
}
