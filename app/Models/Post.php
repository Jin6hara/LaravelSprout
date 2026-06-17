<?php

/**
 * 掲示板の投稿（通達・お知らせ）を管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Pivots\PostViewer;
use App\Models\Attachment;
use App\Enums\PostType;

class Post extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'body', 'expires_at', 'allow_replies'];

    protected $casts = [
        'expires_at'    => 'datetime',
        'allow_replies' => 'boolean',
        'type'          => PostType::class, // ← enum キャスト
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function viewers()
    {
        // Pivot に confirmed_at を含める
        return $this->belongsToMany(User::class, 'post_user')
            ->using(PostViewer::class)
            ->withPivot(['confirmed_at'])
            ->withTimestamps();
    }

    // 添付ファイルの多態的リレーション
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** 自分が閲覧可能な Post に絞る */
    /** 有効期限も考慮した可視スコープ（投稿者は期限後も閲覧可） */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->where(function ($q) use ($user) {
            $q->where('user_id', $user->id) // 投稿者
                ->orWhere(function ($q) use ($user) {
                    $q->whereHas('viewers', fn($v) => $v->where('users.id', $user->id))
                        ->where(function ($q) {
                            $q->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now('Asia/Tokyo'));
                        });
                });
        });
    }

    /** 未読（= confirmed_at IS NULL）だけに絞る */
    public function scopeUnreadFor(Builder $q, User $user): Builder
    {
        return $q->visibleTo($user)
            ->whereHas('viewers', function ($v) use ($user) {
                $v->where('user_id', $user->id)->whereNull('confirmed_at');
            });
    }


    public function isExpired(): bool
    {
        return $this->expires_at !== null && now('Asia/Tokyo')->greaterThanOrEqualTo($this->expires_at);
    }

    public function allowsReplies(): bool
    {
        return $this->allow_replies && !$this->isExpired();
    }
}
