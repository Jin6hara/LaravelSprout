<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'employee_code',
        'password',
        'email_verified_at',
        'gender',
        'role',
        'phone_number',
        'address',
        'profile_picture',
        'self_introduction',
        'created_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getRouteKeyName()
    {
        return 'employee_code';
    }

    public function getProfileImageUrlAttribute(): string
    {
        $defaults = config('user.default_profile_pictures');
        $file = $this->profile_picture ?: $defaults[$this->gender];
        return asset('image/' . ltrim($file, '/'));
    }

    public function getGenderLabelAttribute(): string
    {
        return match ($this->gender) {
            'male' => '男性',
            'female' => '女性',
            'other' => 'その他',
            default => '未選択',
        };
    }

    public function employmentTerms()
    {
        return $this->hasMany(EmploymentTerm::class);
    }

    public function currentEmploymentTerm(?Carbon $date = null)
    {
        $d = ($date ?? now())->toDateString();
        return $this->employmentTerms()
            ->whereDate('start_date', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
            });
    }

    /**
     * 今日“在籍として扱う”ユーザー
     * 在籍期間には含まれるが、休職には該当しない
     */
    public function scopeActiveAt($q, ?Carbon $date = null)
    {
        $d = ($date ?? today())->toDateString();

        return $q->whereHas('employmentTerms', function ($term) use ($d) {
            $term->currentAt($d)
                ->whereDoesntHave('leavePeriods', fn($leave) => $leave->coversDate($d));
        });
    }

    /** 今日 休職中（在籍だが就業停止） */
    public function scopeOnLeaveAt($q, ?Carbon $date = null)
    {
        $d = ($date ?? today())->toDateString();

        return $q->whereHas('employmentTerms', function ($term) use ($d) {
            $term->currentAt($d)
                ->whereHas('leavePeriods', fn($leave) => $leave->coversDate($d));
        });
    }

    /** 入社待ち（未来開始の雇用期間がある） */
    public function scopePrehire($q, ?Carbon $date = null)
    {
        $d = ($date ?? today())->toDateString();
        return $q->whereHas('employmentTerms', fn($t) => $t->whereDate('start_date', '>', $d));
    }

    /** 退職済（今日有効な雇用期間なし＆将来の開始もなし） */
    public function scopeTerminated($q, ?Carbon $date = null)
    {
        $d = ($date ?? today())->toDateString();
        return $q->whereDoesntHave(
            'employmentTerms',
            fn($t) =>
            $t->whereDate('start_date', '>', $d)
                ->orWhere(function ($tt) use ($d) {
                    $tt->whereDate('start_date', '<=', $d)
                        ->where(function ($qq) use ($d) {
                            $qq->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
                        });
                })
        );
    }

    /** 派生プロパティ：現在の状態（active/on_leave/prehire/terminated） */
    public function getEmploymentStateAttribute(): string
    {
        $d = today()->toDateString();

        // 入社待ち（未来開始が1つでもあれば prehire 扱い）
        if ($this->employmentTerms()->whereDate('start_date', '>', $d)->exists()) {
            return '入社待ち/prehire';
        }

        // 現在有効な雇用期間
        $term = $this->currentEmploymentTerm(today())->first();
        if (!$term) {
            return '退職済み/terminated';
        }

        // 有効期間中に休職が重なっていれば on_leave
        if ($term->leavePeriods()->coversDate($d)->exists()) {
            return '休職中/on_leave';
        }

        return '在籍中/active';
    }

    /** 通知や自動メールの基本判定 */
    public function shouldReceiveOperationalEmails(?Carbon $date = null): bool
    {
        $state = $this->employment_state; // 上のアクセサで取得
        return $state === 'active';
    }
}
