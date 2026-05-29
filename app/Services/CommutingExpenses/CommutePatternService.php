<?php

namespace App\Services\CommutingExpenses;

use App\Models\CommutePattern;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CommutePatternService
{
    public const DOW_VALUES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public const DOW_ORDER = "CASE dow WHEN 'Sun' THEN 0 WHEN 'Mon' THEN 1 WHEN 'Tue' THEN 2 WHEN 'Wed' THEN 3 WHEN 'Thu' THEN 4 WHEN 'Fri' THEN 5 WHEN 'Sat' THEN 6 ELSE 99 END";

    public function allForUser(User $user): Collection
    {
        return CommutePattern::query()
            ->where('user_id', $user->id)
            ->with([
                'legs' => fn ($q) => $q->orderByRaw(self::DOW_ORDER)->orderBy('seq')->orderBy('id'),
            ])
            ->orderByDesc('valid_from')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();
    }

    public function activeOn(User $user, Carbon $date): ?CommutePattern
    {
        return CommutePattern::query()
            ->where('user_id', $user->id)
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->whereDate('valid_to', '>=', $date->toDateString())
            ->with([
                'legs' => fn ($q) => $q->orderByRaw(self::DOW_ORDER)->orderBy('seq')->orderBy('id'),
            ])
            ->orderByDesc('valid_from')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->first();
    }

    public function activeOrLatest(User $user, ?Carbon $date = null): ?CommutePattern
    {
        $date ??= now('Asia/Tokyo');

        return $this->activeOn($user, $date)
            ?: CommutePattern::query()
                ->where('user_id', $user->id)
                ->with([
                    'legs' => fn ($q) => $q->orderByRaw(self::DOW_ORDER)->orderBy('seq')->orderBy('id'),
                ])
                ->orderByDesc('valid_from')
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->first();
    }

    public function forPeriod(User $user, Carbon $start, Carbon $end): Collection
    {
        return CommutePattern::query()
            ->where('user_id', $user->id)
            ->whereDate('valid_from', '<=', $end->toDateString())
            ->whereDate('valid_to', '>=', $start->toDateString())
            ->with([
                'legs' => fn ($q) => $q->orderByRaw(self::DOW_ORDER)->orderBy('seq')->orderBy('id'),
            ])
            ->orderByDesc('valid_from')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();
    }
}
