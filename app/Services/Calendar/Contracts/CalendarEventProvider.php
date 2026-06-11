<?php

/**
 * カレンダーイベントを提供するプロバイダの共通インターフェース。
 */
namespace App\Services\Calendar\Contracts;

use App\Models\User;
use Carbon\Carbon;

interface CalendarEventProvider
{
    /** @return array<CandidateEvent> */
    public function provide(User $user, Carbon $start, Carbon $end): array;
}
