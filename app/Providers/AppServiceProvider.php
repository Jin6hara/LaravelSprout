<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use App\Services\Calendar\CalendarResolver;
use App\Services\Calendar\Contracts\CalendarEventProvider;
use App\Models\Leave;
use App\Models\Event;
use App\Observers\LeaveObserver;
use App\Observers\EventObserver;
use App\Services\Calendar\ForecastResolver;
use App\Services\Calendar\LeaveResolver;
use App\Services\Calendar\Providers\HolidayProvider;
use App\Services\Calendar\Providers\ClosureProvider;
use App\Services\Calendar\Providers\SubCountProvider;
use App\Services\Calendar\Providers\AllEventProvider;
use App\Services\Calendar\Providers\AllLeaveProvider;
use App\Support\DatabaseText;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\URL;

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
            \App\Services\Calendar\Providers\EventProvider::class,
            \App\Services\Calendar\Providers\SubProvider::class,
        ], 'calendar.providers');

        // Resolver をシングルトンで用意し、タグ経由で Provider 群を注入
        $this->app->singleton(CalendarResolver::class, function ($app) {
            $providers = $app->tagged('calendar.providers');
            return new CalendarResolver($providers);
        });

        $this->app->singleton(ForecastResolver::class, function ($app) {
            // ここで「祝日」と「会社長期休み」だけに限定
            $providers = [
                $app->make(HolidayProvider::class),
                $app->make(ClosureProvider::class),
                $app->make(SubCountProvider::class),
                $app->make(AllEventProvider::class),
            ];
            return new ForecastResolver($providers);
        });

        $this->app->singleton(LeaveResolver::class, function ($app) {
            // ここで「祝日」と「会社長期休み」だけに限定
            $providers = [
                $app->make(HolidayProvider::class),
                $app->make(ClosureProvider::class),
                $app->make(AllLeaveProvider::class),
            ];
            return new LeaveResolver($providers);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootQueryTextMacros();

        Paginator::useBootstrapFive(); //user.userListのpagination用に
        Leave::observe(LeaveObserver::class);
        Event::observe(EventObserver::class);

        View::composer('*', function ($view) {
            $user = auth()->user();
            if (!$user) return;

            // 代理閲覧中でもヘッダーは「自分の未確認」を出す想定
            $inboxUnconfirmed = $user->postsVisible()
                ->wherePivotNull('confirmed_at')
                ->count();

            $view->with('inboxUnconfirmed', $inboxUnconfirmed);
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }

    private function bootQueryTextMacros(): void
    {
        if (!QueryBuilder::hasMacro('whereLikeInsensitive')) {
            QueryBuilder::macro('whereLikeInsensitive', function (string $column, ?string $value, string $boolean = 'and') {
                return DatabaseText::whereContainsInsensitive($this, $column, $value, $boolean);
            });
        }

        if (!QueryBuilder::hasMacro('orWhereLikeInsensitive')) {
            QueryBuilder::macro('orWhereLikeInsensitive', function (string $column, ?string $value) {
                return DatabaseText::whereContainsInsensitive($this, $column, $value, 'or');
            });
        }

        if (!QueryBuilder::hasMacro('whereEqualsInsensitive')) {
            QueryBuilder::macro('whereEqualsInsensitive', function (string $column, ?string $value, string $boolean = 'and') {
                return DatabaseText::whereEqualsInsensitive($this, $column, $value, $boolean);
            });
        }

        if (!QueryBuilder::hasMacro('orWhereEqualsInsensitive')) {
            QueryBuilder::macro('orWhereEqualsInsensitive', function (string $column, ?string $value) {
                return DatabaseText::whereEqualsInsensitive($this, $column, $value, 'or');
            });
        }

        EloquentBuilder::macro('whereLikeInsensitive', function (string $column, ?string $value, string $boolean = 'and') {
            return DatabaseText::whereContainsInsensitive($this, $column, $value, $boolean);
        });

        EloquentBuilder::macro('orWhereLikeInsensitive', function (string $column, ?string $value) {
            return DatabaseText::whereContainsInsensitive($this, $column, $value, 'or');
        });

        EloquentBuilder::macro('whereEqualsInsensitive', function (string $column, ?string $value, string $boolean = 'and') {
            return DatabaseText::whereEqualsInsensitive($this, $column, $value, $boolean);
        });

        EloquentBuilder::macro('orWhereEqualsInsensitive', function (string $column, ?string $value) {
            return DatabaseText::whereEqualsInsensitive($this, $column, $value, 'or');
        });
    }
}
