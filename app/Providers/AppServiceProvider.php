<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Services\Calendar\CalendarResolver;
use App\Services\Calendar\Contracts\CalendarEventProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Provider群をタグ付け（ここに作ったProviderを列挙）
        $this->app->tag([
            \App\Services\Calendar\Providers\HolidayProvider::class,
            \App\Services\Calendar\Providers\AdjustingProvider::class,
            \App\Services\Calendar\Providers\OffDayProvider::class,
            \App\Services\Calendar\Providers\ClosureProvider::class,
            \App\Services\Calendar\Providers\WorkProvider::class,
            \App\Services\Calendar\Providers\LeaveProvider::class,
            \App\Services\Calendar\Providers\OvertimeProvider::class,
        ], 'calendar.providers');

        // Resolver をシングルトンで用意し、タグ経由で Provider 群を注入
        $this->app->singleton(CalendarResolver::class, function ($app) {
            $providers = $app->tagged('calendar.providers');
            return new CalendarResolver($providers);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive(); //user.userListのpagination用に
    }
}
