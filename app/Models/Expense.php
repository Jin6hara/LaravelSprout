<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseTripType;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\ExpenseObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([ExpenseObserver::class])]
class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'expense_report_id',
        'expense_date',
        'seq',
        'station_from',
        'station_to',
        'note',
        'cost',
        'trip_type',
        'category',
        'commuter_pass_id',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'trip_type'    => ExpenseTripType::class,
        'category'     => ExpenseCategory::class,
        'cost'         => 'integer',
    ];

    // 親の更新日時を自動で更新
    protected $touches = ['report'];

    /* Relations */
    public function report()
    {
        return $this->belongsTo(ExpenseReport::class, 'expense_report_id');
    }


    public function commuterPass()
    {
        return $this->belongsTo(CommuterPass::class);
    }

    /* Scopes */
    public function scopeForDate($q, string|\Carbon\Carbon $date)
    {
        return $q->whereDate('expense_date', ($date instanceof \Carbon\Carbon) ? $date->toDateString() : $date);
    }
}
