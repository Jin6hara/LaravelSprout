<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Calendar\CalendarResolver;
use App\Services\Calendar\EventType;
use App\Services\Calendar\PlanGroup;
use App\Services\CurrentScopeService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserAttendanceSearchController extends Controller
{
    public function __construct(
        private CurrentScopeService $scopeService,
        private CalendarResolver $resolver
    ) {}

    public function attendance(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'regular_on' => ['nullable', 'boolean'],
        ]);

        $date = Carbon::parse($validated['date'] ?? today()->toDateString())->startOfDay();
        $mode = $request->boolean('regular_on') ? 'regular_on' : 'available';
        $searched = $request->hasAny(['date', 'regular_on']);

        $results = collect();

        if ($searched) {
            $users = $this->scopeService->targetUserQuery()
                ->whereHas('employmentTerms', fn (Builder $term) => $term->currentAt($date->toDateString()))
                ->with([
                    'district',
                    'department',
                    'employmentTerms' => fn ($q) => $q
                        ->currentAt($date->toDateString())
                        ->orderByDesc('start_date'),
                    'restPatternAssignments' => fn ($q) => $q
                        ->with('pattern')
                        ->activeBetween($date->toDateString(), $date->toDateString())
                        ->orderByDesc('start_date'),
                ])
                ->orderBy('employee_code')
                ->get();

            $results = $users
                ->map(fn (User $user) => $this->buildRow($user, $date))
                ->filter(fn (array $row) => $mode === 'regular_on'
                    ? $row['has_regular_on']
                    : (! $row['has_on'] && ! $row['has_off']))
                ->values();
        }

        return view('user.search.attendance', [
            'date' => $date->toDateString(),
            'mode' => $mode,
            'searched' => $searched,
            'results' => $results,
        ]);
    }

    private function buildRow(User $user, Carbon $date): array
    {
        $events = collect($this->resolver->build($user, $date->copy()->startOfDay(), $date->copy()->endOfDay()));
        $employmentTerm = $user->employmentTerms->first();
        $restPatternAssignment = $user->restPatternAssignments->first();

        $hasOn = $events->contains(fn (array $event) => $this->eventType($event) === EventType::ON);
        $hasOff = $events->contains(fn (array $event) => $this->eventType($event) === EventType::OFF);
        $hasRegularOn = $events->contains(fn (array $event) =>
            $this->eventType($event) === EventType::ON
            && $this->planGroup($event) === PlanGroup::REGULAR_PLAN
        );

        return [
            'user' => $user,
            'has_on' => $hasOn,
            'has_off' => $hasOff,
            'has_regular_on' => $hasRegularOn,
            'type_code' => $employmentTerm?->type_code,
            'rest_pattern' => $restPatternAssignment?->pattern?->name,
        ];
    }

    private function eventType(array $event): ?string
    {
        return $event['extendedProps']['type'] ?? $event['display'] ?? null;
    }

    private function planGroup(array $event): ?string
    {
        return $event['extendedProps']['plan_group']
            ?? $event['extendedProps']['plan']
            ?? null;
    }
}
