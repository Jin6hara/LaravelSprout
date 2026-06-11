<?php

/**
 * 地区（エリア）情報と親子階層を管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'name'];

    public function parent()
    {
        return $this->belongsTo(District::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(District::class, 'parent_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function schools()
    {
        return $this->hasMany(School::class);
    }
}
