<?php

/**
 * 休日パターン（勤務カレンダーの曜日ルール）のマスターを管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestPattern extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];

    public function rules(): HasMany
    {
        return $this->hasMany(RestPatternRule::class);
    }
}
