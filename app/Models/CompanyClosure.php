<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CompanyClosure extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'start_date', 'end_date', 'is_active', 'note'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];

    // 期間が重なるもの
    public function scopeBetween(Builder $q, $start, $end): Builder
    {
        return $q->where('is_active', true)
            ->whereDate('end_date', '>=', $start)
            ->whereDate('start_date', '<=', $end);
    }
}
