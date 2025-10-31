<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolProfile extends Model
{
    protected $fillable = [
        'school_id', 'address', 'description', 'map_image_path',
        'valid_from', 'valid_to',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to'   => 'datetime',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function stations()
    {
        return $this->hasMany(SchoolStation::class)->orderBy('sort_order');
    }
}
