<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        // 毎月 2 日の 0:00 に当月分を自動生成
        $schedule->command('expenses:generate-monthly-by-pattern')
            ->monthlyOn(2, '0:00')
            ->withoutOverlapping();

        // 毎月 3 日の 0:00 に「2ヶ月前」をクリーンアップ
        $schedule->command('expenses:cleanup-empty')
            ->monthlyOn(3, '0:00')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    protected $commands = [
        \App\Console\Commands\GrantLeaveDays::class, //php artisan leave:grant 10 --date=2025-04-13 一斉付与　（テスト用）
    ];
}
