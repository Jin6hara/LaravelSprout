<?php

namespace App\Providers;

use App\Models\ApprovalRequest;
use App\Models\Department;
use App\Models\District;
use App\Models\Event;
use App\Models\Expense;
use App\Models\ExpenseReport;
use App\Models\Lesson;
use App\Models\ScheduleDetail;
use App\Models\ScheduleLine;
use App\Models\UserManagementScope;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DistrictPolicy;
use App\Policies\EventPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\ExpenseReportPolicy;
use App\Policies\LessonPolicy;
use App\Policies\ScheduleDetailPolicy;
use App\Policies\ScheduleLinePolicy;
use App\Policies\UserManagementScopePolicy;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\User::class                => \App\Policies\UserPolicy::class,
        \App\Models\Leave::class               => \App\Policies\LeavePolicy::class,
        \App\Models\Post::class                => \App\Policies\PostPolicy::class,
        ApprovalRequest::class                 => ApprovalRequestPolicy::class,
        Department::class                      => DepartmentPolicy::class,
        District::class                        => DistrictPolicy::class,
        Event::class                           => EventPolicy::class,
        ExpenseReport::class                   => ExpenseReportPolicy::class,
        Expense::class                         => ExpensePolicy::class,
        Lesson::class                          => LessonPolicy::class,
        ScheduleLine::class                    => ScheduleLinePolicy::class,
        ScheduleDetail::class                  => ScheduleDetailPolicy::class,
        UserManagementScope::class             => UserManagementScopePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('view-notification', function (\App\Models\User $user, DatabaseNotification $notification): bool {
            return $notification->notifiable_type === \App\Models\User::class
                && (int) $notification->notifiable_id === $user->id;
        });
    }
}
