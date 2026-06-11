<?php

/**
 * 掲示板投稿の種別（お知らせ／問い合わせ／DM／緊急通知／メンテナンス等）を表すEnum。
 */
namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PostType: string
{
    use HasValues;

    case Announcement  = 'announcement';
    case Inquiry       = 'inquiry';
    case DirectMessage = 'dm';
    case NoticeUrgent  = 'notice_urgent';
    case Maintenance   = 'maintenance';
    case Other         = 'other';

    public static function sendValues(): array
    {
        return [
            self::Announcement->value,
            self::Inquiry->value,
            self::DirectMessage->value,
            self::NoticeUrgent->value,
            self::Maintenance->value,
        ];
    }

    // タイプ別の既定値（UI/保存時の既定）
    public function defaultAllowReplies(): bool
    {
        return match ($this) {
            self::Announcement, self::Maintenance => false,
            self::Inquiry, self::DirectMessage, self::NoticeUrgent, self::Other => true,
        };
    }
}
