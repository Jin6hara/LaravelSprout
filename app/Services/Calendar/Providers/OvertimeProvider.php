<?php

namespace App\Services\Calendar\Providers;

use App\Models\User;
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class OvertimeProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        return []; // 後で残業テーブルから生成
    }
}
