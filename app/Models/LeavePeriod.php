<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function scopeCoversDate($q, ?string $date = null)
    {
        $d = ($date ?? today()->toDateString());
        return $q->whereDate('start_date', '<=', $d)
            ->whereDate('end_date', '>=', $d);
    }

    public function isActive(): bool
    {
        $today = now()->startOfDay();
        $started = $this->start_date?->startOfDay()->lte($today) ?? false;
        $notEnded = is_null($this->end_date) || $this->end_date->endOfDay()->gte($today);

        return $started && $notEnded;
    }
}
