<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class EmploymentTerm extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'start_date', 'end_date', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function leavePeriods()
    {
        return $this->hasMany(LeavePeriod::class);
    }

    public function scopeCurrentAt($q, ?string $date = null)
    {
        $d = ($date ?? today()->toDateString());
        return $q->whereDate('start_date', '<=', $d)
            ->where(function ($qq) use ($d) {
                $qq->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
            });
    }
}
