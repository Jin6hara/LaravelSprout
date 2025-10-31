<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sub extends Model
{
    use HasFactory;

    protected $table = 'subs';

    protected $fillable = [
        'user_id',
        'sub_date',
        'start_time',
        'end_time',
        'total_duration', // minutes
    ];

    protected $casts = [
        'sub_date'   => 'date',
        // TIME型をCarbonにしたい場合は datetime キャスト（H:i で時刻のみ）
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
    ];

    /** ユーザー */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** 便利スコープ：日付範囲 */
    public function scopeBetweenDates($q, $from, $to)
    {
        return $q->whereBetween('sub_date', [$from, $to]);
    }

    /** 表示用：合計時間を H:MM で取得（例: 95分 → 1:35） */
    public function getTotalDurationHmAttribute(): string
    {
        $m = (int) $this->total_duration;
        $h = intdiv($m, 60);
        $r = $m % 60;
        return sprintf('%d:%02d', $h, $r);
    }

    /** モデルイベント：保存時に total_duration を計算してセット */
    protected static function booted()
    {
        static::saving(function ($sub) {
            if ($sub->start_time && $sub->end_time) {
                // Carbonで差分を分単位で算出
                $start = \Carbon\Carbon::parse($sub->start_time);
                $end   = \Carbon\Carbon::parse($sub->end_time);
                $sub->total_duration = $end->diffInMinutes($start);
            }
        });
    }
}
