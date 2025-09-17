<?php

namespace App\Models;

use App\Enums\ExpenseReportStatus;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\ExpenseReportObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ExpenseReportObserver::class])]
class ExpenseReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_code',
        'employee_family_name',
        'employee_first_middle_name',
        'year',
        'month',
        'status',
        'submitted_at',
        'approved_at',
        'paid_at',
        'total_amount',
    ];

    protected $casts = [
        'status'       => ExpenseReportStatus::class,
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'paid_at'      => 'datetime',
    ];

    /* Relations */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /* Accessors */
    public function getEmployeeNameAttribute(): string
    {
        return trim($this->employee_family_name . ' ' . $this->employee_first_middle_name);
    }

    /* Scopes */
    public function scopeForYearMonth($q, int $year, int $month)
    {
        return $q->where('year', $year)->where('month', $month);
    }
}
