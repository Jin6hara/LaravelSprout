<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CompanyClosure extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'name', 'type', 'is_full_day', 'meta'];
    protected $casts = ['date' => 'date', 'meta' => 'array', 'is_full_day' => 'boolean'];

    public function scopeBetween(Builder $q, $start, $end): Builder
    {
        return $q->whereBetween('date', [date($start), date($end)]);
    }

    public function toCalendarEvent(): array
    {
        return [
            'title' => $this->name,
            'start' => $this->date->toDateString(),
            'allDay' => $this->is_full_day,
            'display' => 'background',
            'backgroundColor' => '#e6f7ff',
            'borderColor' => '#cceeff',
            'extendedProps' => [
                'category' => 'company_off',
                'type' => $this->type,
            ],
        ];
    }
}
