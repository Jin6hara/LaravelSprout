<?php
// app/Console/Commands/GrantLeaveDays.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LeaveBalanceService;
use App\Models\User;
use Carbon\Carbon;

class GrantLeaveDays extends Command
{
    protected $signature = 'leave:grant {days} {--date=}';
    protected $description = '全ユーザーに有給日数を付与';

    public function handle(): int
    {
        $days = (float)$this->argument('days');
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now();

        $svc = app(LeaveBalanceService::class);
        $users = User::all();
        foreach ($users as $u) {
            $svc->grantDays($u->id, $date, $days, 'annual grant');
        }
        $this->info("Granted {$days} days to " . count($users) . " users for {$date->toDateString()}");
        return self::SUCCESS;
    }
}
