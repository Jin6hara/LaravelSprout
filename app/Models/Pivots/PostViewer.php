<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PostViewer extends Pivot
{
    protected $table = 'post_user';
    protected $fillable = ['confirmed_at'];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function markAsRead(): void
    {
        $this->confirmed_at = now();
        $this->save();
    }

    public function markAsUnread(): void
    {
        $this->confirmed_at = null;
        $this->save();
    }
}
