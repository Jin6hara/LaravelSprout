<?php

namespace App\Services\Calendar\Providers;

use App\Models\User;
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class LeaveProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        return []; // 後で有給テーブルから生成
    }
}
