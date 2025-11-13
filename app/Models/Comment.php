<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Comment extends Model
{
    protected $fillable = ['post_id', 'user_id', 'parent_id', 'body'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }
    // 子（直下）
    public function children()
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at');
    }

    // 再帰で子孫まで
    public function childrenRecursive()
    {
        return $this->children()->with(['author', 'childrenRecursive']);
    }

    /** 「ポストが見える」かつ「自分のコメント or その返信（1段）」だけを返す */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->whereHas('post', fn($p) => $p->visibleTo($user))
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('parent', fn($p) => $p->where('user_id', $user->id));
            });
    }
}
