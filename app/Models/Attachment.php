<?php

/**
 * 各モデルに多態的に紐づく添付ファイル情報を管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = ['path', 'original_name', 'size'];

    public function attachable()
    {
        return $this->morphTo();
    }
}
