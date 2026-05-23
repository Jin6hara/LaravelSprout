<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserManagementScope extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'district_id', 'department_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
