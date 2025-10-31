<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStation extends Model
{
    protected $fillable = [
        'school_profile_id', 'station_name', 'line',
        'walk_minutes', 'guide_text', 'guide_image_path', 'sort_order',
    ];

    public function profile()
    {
        return $this->belongsTo(SchoolProfile::class, 'school_profile_id');
    }
}
