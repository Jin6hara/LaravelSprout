<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommuterPass extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date_from',
        'date_to',
        'station_from',
        'station_to',
        'note',
        'cost',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to'   => 'date',
        'cost'      => 'integer',
    ];

    /* Relations */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
