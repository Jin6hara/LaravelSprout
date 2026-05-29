<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Pivots\PostViewer;
use Illuminate\Support\Facades\DB;

/**
 * @mixin \Spatie\Permission\Traits\HasRoles
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'family_name',
        'first_name',
        'middle_name',
        'name_in_kana',
        'email',
        'employee_code',
        'password',
        'email_verified_at',
        'gender',
        'phone_number',
        'address',
        'profile_picture',
        'note',
        'created_at',
        'district_id',
        'department_id',
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

    /**
     * The name of the guard used by this model.
     * This is used by Spatie's Permission package to determine the guard for roles and permissions.
     *
     * @var string
     * @see https://spatie.be/docs/laravel-permission/v5/basic-usage/guards-and-multi-auth#guard_name
     */
    protected string $guard_name = 'web';

    // family_name, first_name, middle_name のどれかが更新されたら name は自動更新する
    public function setFamilyNameAttribute($value)
    {
        $this->attributes['family_name'] = $value;
        $this->updateFullName();
    }

    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = $value;
        $this->updateFullName();
    }

    public function setMiddleNameAttribute($value)
    {
        $this->attributes['middle_name'] = $value;
        $this->updateFullName();
    }

    /**
     * family_name, first_name, middle_name のどれかが更新されたら name を自動更新
     */
    protected function updateFullName(): void
    {
        $last   = $this->attributes['family_name'] ?? '';
        $first  = $this->attributes['first_name'] ?? '';
        $middle = $this->attributes['middle_name'] ?? '';

        // 順番: Last → First → Middle
        $fullName = trim(implode(' ', array_filter([$last, $first, $middle])));

        $this->attributes['name'] = $fullName;
    }

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
        return Gender::tryFrom((string) $this->gender)?->label() ?? Gender::Unknown->label();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(['admin', 'super_admin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function getRoleLabelAttribute(): string
    {
        $role = $this->getRoleNames()->first();

        if (!$role) {
            return '未設定';
        }

        $map = [
            'general'      => '一般',
            'trainer'      => 'トレーナー',
            'admin'        => '管理者',
            'super_admin'  => 'スーパー管理者',
        ];

        return $map[$role] ?? $role; // 未定義ロールは英字のまま表示
    }

    public function employmentTerms()
    {
        return $this->hasMany(EmploymentTerm::class);
    }

    public function restPatternAssignments()
    {
        return $this->hasMany(UserRestPattern::class);
    }

    /** 最新の雇用期間を1件だけ取得（N+1 を避けるための hasOne + latestOfMany） */
    public function latestEmploymentTerm()
    {
        return $this->hasOne(EmploymentTerm::class)->latestOfMany('start_date');
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

    public function scheduleLines(): HasMany
    {
        return $this->hasMany(ScheduleLine::class);
    }

    public function expenseReports()
    {
        return $this->hasMany(ExpenseReport::class);
    }

    public function commuterPasses()
    {
        return $this->hasMany(CommuterPass::class);
    }

    /**
     * ユーザーに紐づく全Expense（ExpenseReport 経由）
     */
    public function expenses()
    {
        return $this->hasManyThrough(
            Expense::class,         // 目標
            ExpenseReport::class,   // 中間
            'user_id',              // ExpenseReport 側のFK（users.id を指す）
            'expense_report_id',    // Expense 側のFK（expense_reports.id を指す）
            'id',                   // User ローカルキー
            'id'                    // ExpenseReport ローカルキー
        );
    }

    public function postsAuthored()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
    public function postsVisible()
    {
        return $this->belongsToMany(Post::class, 'post_user')
            ->using(PostViewer::class)
            ->withPivot(['confirmed_at'])
            ->withTimestamps();
    }

    public function inboxUnconfirmedCount(): int
    {
        return (int) DB::table('post_user')
            ->where('user_id', $this->id)
            ->whereNull('confirmed_at')
            ->count();
    }

    public function commentsAuthored()
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    public function routeDeclarations()
    {
        return $this->hasMany(RouteDeclaration::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * admin/super_admin が管理できる district×department の組み合わせ一覧
     */
    public function managementScopes()
    {
        return $this->hasMany(UserManagementScope::class);
    }
}
