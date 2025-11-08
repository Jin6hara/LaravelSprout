<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Pivots\PostViewer;
use App\Models\Attachment;

class Post extends Model
{
    protected $fillable = ['user_id', 'title', 'body'];

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

    // 添付ファイルにの多態的リレーション
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** 自分が閲覧可能な Post に絞る */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhereHas('viewers', fn($v) => $v->where('users.id', $user->id));
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
}
