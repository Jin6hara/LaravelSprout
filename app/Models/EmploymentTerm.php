<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class EmploymentTerm extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'start_date', 'end_date', 'note'];

    //これで $employment->start_date が自動的に Carbon になり、Bladeで ->format('Y/m/d') が使えるようになる。
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

    public function scopeCurrentAt($q, ?string $date = null)
    {
        $d = ($date ?? today()->toDateString());
        return $q->whereDate('start_date', '<=', $d)
            ->where(function ($qq) use ($d) {
                $qq->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
            });
    }
}
