<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserScheduleAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'schedule_id', 'start_date', 'end_date'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
    public function scopeActiveBetween($q, $s, $e)
    {
        return $q->whereDate('end_date', '>=', $s)->whereDate('start_date', '<=', $e);
    }
    public function scopeActiveOn($q, Carbon $d)
    {
        return $q->whereDate('start_date', '<=', $d)->whereDate('end_date', '>=', $d);
    }
}
