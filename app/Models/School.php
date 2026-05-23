<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_code', 'school_name', 'name_kana', 'aliases', 'is_active', 'district_id',
    ];

    protected $casts = [
        'aliases'   => 'array',
        'is_active' => 'boolean',
    ];

    public function profiles()
    {
        return $this->hasMany(SchoolProfile::class);
    }

    public function currentProfile()
    {
        return $this->hasOne(SchoolProfile::class)
            ->whereNull('valid_to')
            ->latest('valid_from');
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }
}
