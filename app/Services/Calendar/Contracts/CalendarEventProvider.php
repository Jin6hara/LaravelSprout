<?php

namespace App\Services\Calendar\Contracts;

use App\Models\User;
use Carbon\Carbon;

interface CalendarEventProvider
{
    /** @return array<CandidateEvent> */
    public function provide(User $user, Carbon $start, Carbon $end): array;
}
