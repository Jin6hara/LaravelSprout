<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class EmploymentTerm extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'start_date', 'end_date', 'type_name', 'type_code', 'note'];

    // これで $employment->start_date が自動的に Carbon になり、Bladeで ->format('Y/m/d') が使えるようになる。
    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leavePeriods()
    {
        return $this->hasMany(LeavePeriod::class);
    }

    /** 今日の日付で有効な雇用期間を取得するスコープ */
    public function scopeCurrentAt(Builder $q, $date = null): Builder
    {
        $d = $date instanceof \Carbon\CarbonInterface
            ? $date->toDateString()
            : ($date ?? today()->toDateString());

        return $q->where('start_date', '<=', $d)
            ->where(function ($qq) use ($d) {
                $qq->whereNull('end_date')->orWhere('end_date', '>=', $d);
            });
    }

    /** 今日の日付で有効な休職期間を持つ雇用期間を取得するスコープ */
    public function activeLeavePeriods($date = null): \Illuminate\Support\Collection
    {
        return $this->leavePeriods->filter(fn($lp) => $lp->isActive($date))->values();// 引数いる場合
        // $this->leavePeriods->filter->isActive()->values();も同じ意味ですが引数無に限定
    }
}
