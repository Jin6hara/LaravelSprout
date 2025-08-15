<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class LeavePeriod extends Model
{
    use HasFactory;

    protected $fillable = ['employment_term_id', 'start_date', 'end_date', 'reason'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function employmentTerm()
    {
        return $this->belongsTo(EmploymentTerm::class);
    }

    //** 今日の日付で有効な休職期間を取得するスコープ */
    public function scopeCoversDate(Builder $q, $date = null): Builder
    {
        $d = $date ?? today()->toDateString();//文字例のみ。
        return $q->where('start_date', '<=', $d)
            ->where(function ($qq) use ($d) {
                $qq->whereNull('end_date')->orWhere('end_date', '>=', $d);
            });
    }

    /** 休職期間が今日の日付で有効かどうか */
    public function isActive($date = null): bool
    {
        $d = ($date ?? today())->startOfDay();
        $started  = $this->start_date?->startOfDay()->lte($d) ?? false;
        $notEnded = is_null($this->end_date) || $this->end_date->endOfDay()->gte($d);
        return $started && $notEnded;
    }
}
