<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Support\SchoolName;

class ScheduleLine extends Model
{
    use HasFactory;

    protected $fillable = ['schedule_id', 'parent_line_id', 'dow', 'school_name', 'start_time', 'end_time', 'effective_start', 'effective_end', 'handover_memo'];
    protected $casts = ['effective_start' => 'date', 'effective_end' => 'date'];

    public function setSchoolNameAttribute($value): void
    {
        $this->attributes['school_name'] = SchoolName::normalize($value);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_line_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_line_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function scopeActiveOn($q, Carbon $d)
    {
        return $q->whereDate('effective_start', '<=', $d)->whereDate('effective_end', '>=', $d);
    }

    public function scopeOverlapping($q, string $from, string $to)
    {
        return $q->whereDate('effective_start', '<=', $to)->whereDate('effective_end', '>=', $from);
    }

    public function details()
    {
        return $this->hasMany(ScheduleDetail::class);
    }
}
