<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = ['date','name','name_en','country_code','year','is_observed','source','meta'];
    protected $casts = ['date' => 'date', 'meta' => 'array', 'is_observed' => 'boolean'];

    public function scopeBetween(Builder $q, $start, $end): Builder {
        return $q->whereBetween('date', [date($start), date($end)]);
    }

    public function toCalendarEvent(): array {
        return [
            'title' => $this->name,
            'start' => $this->date->toDateString(),
            'allDay' => true,
            'display' => 'background', // 背景で休業表現（前回のエンジンに合わせて必要ならnormalへ）
            'backgroundColor' => '#ffe6e6',
            'borderColor' => '#ffcccc',
            'extendedProps' => [
                'category' => 'holiday',
                'is_observed' => $this->is_observed,
                'source' => $this->source,
            ],
        ];
    }
}

