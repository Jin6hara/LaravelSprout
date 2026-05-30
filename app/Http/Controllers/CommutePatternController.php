<?php

namespace App\Http\Controllers;

use App\Enums\DayOfWeek;
use App\Enums\ExpenseTripType;
use App\Models\CommutePattern;
use App\Models\User;
use App\Services\CommutingExpenses\CommutePatternService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommutePatternController extends Controller
{
    public function selfEdit(Request $request, CommutePatternService $patterns)
    {
        return $this->renderFor($request->user(), $request, false, $patterns);
    }

    public function adminEdit(User $user, Request $request, CommutePatternService $patterns)
    {
        $viewer = $request->user();
        if ($viewer->can('viewAny', User::class) && ! $viewer->can('view', $user)) {
            return redirect()->route('expenses.admin.report', $request->only(['year', 'month']));
        }

        $this->authorize('view', $user);

        return $this->renderFor($user, $request, true, $patterns);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $target = $this->resolveTargetUser($request, $data['user_id'] ?? null);

        $this->authorize('update', $target);

        $pattern = null;
        if (! empty($data['pattern_id'])) {
            abort_unless($request->user()?->hasAnyRole(['admin', 'super_admin']), 403, 'Only admins can edit commute patterns.');

            $pattern = CommutePattern::query()
                ->where('user_id', $target->id)
                ->findOrFail($data['pattern_id']);
        }

        $pattern = DB::transaction(function () use ($data, $target, $pattern) {
            $pattern ??= new CommutePattern;
            $pattern->user_id = $target->id;
            $pattern->fill([
                'submitted_at' => $pattern->submitted_at ?: now('Asia/Tokyo'),
                'closest_station' => $data['closest_station'],
                'train_line' => $data['train_line'] ?? null,
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'],
                'reason' => $data['reason'] ?? null,
            ]);
            $pattern->save();

            $rows = collect($data['rows'])->values();
            $incomingIds = $rows
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($incomingIds->isNotEmpty()) {
                $ownedCount = $pattern->legs()
                    ->whereIn('id', $incomingIds)
                    ->count();

                abort_if($ownedCount !== $incomingIds->count(), 403, 'Some commute pattern leg IDs do not belong to this pattern.');
            }

            $deleteQuery = $pattern->legs();
            if ($incomingIds->isNotEmpty()) {
                $deleteQuery->whereNotIn('id', $incomingIds);
            }
            $deleteQuery->delete();

            foreach ($rows as $index => $row) {
                $payload = [
                    'dow' => $row['dow'],
                    'seq' => (int) ($row['seq'] ?? (($index + 1) * 100)),
                    'station_from' => $row['station_from'] ?? null,
                    'station_to' => $row['station_to'] ?? null,
                    'note' => $row['note'] ?? null,
                    'cost' => (int) ($row['cost'] ?? 0),
                    'trip_type' => $row['trip_type'] ?? ExpenseTripType::ROUND_TRIP->value,
                ];

                if (! empty($row['id'])) {
                    $pattern->legs()->whereKey($row['id'])->firstOrFail()->update($payload);
                } else {
                    $pattern->legs()->create($payload);
                }
            }

            return $pattern->fresh(['legs']);
        });

        return response()->json([
            'ok' => true,
            'pattern_id' => $pattern->id,
            'redirect_url' => $this->routeFor($request, $target, $pattern),
        ]);
    }

    public function destroy(Request $request, CommutePattern $pattern): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'super_admin']), 403, 'Only admins can delete commute patterns.');

        $target = $pattern->user()->firstOrFail();
        $this->authorize('update', $target);

        $pattern->delete();

        return response()->json([
            'ok' => true,
            'redirect_url' => $this->routeFor($request, $target, null, true),
        ]);
    }

    private function renderFor(User $user, Request $request, bool $isAdminMode, CommutePatternService $patterns)
    {
        $canManagePatterns = $request->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
        $allPatterns = $patterns->allForUser($user);
        $patternHistory = CommutePattern::query()
            ->where('user_id', $user->id)
            ->with(['legs' => fn ($q) => $q->orderByRaw(CommutePatternService::DOW_ORDER)->orderBy('seq')->orderBy('id')])
            ->orderByDesc('valid_from')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();
        $selected = null;

        if (! $request->boolean('new')) {
            if ($request->filled('pattern')) {
                $selected = CommutePattern::query()
                    ->where('user_id', $user->id)
                    ->with(['legs' => fn ($q) => $q->orderByRaw(CommutePatternService::DOW_ORDER)->orderBy('seq')->orderBy('id')])
                    ->findOrFail($request->integer('pattern'));
            } else {
                $selected = $patterns->activeOrLatest($user);
            }
        }

        $validFrom = old('valid_from', optional($selected?->valid_from)->toDateString() ?: now('Asia/Tokyo')->toDateString());

        return view('expenses.pattern', [
            'user' => $user,
            'isAdminMode' => $isAdminMode,
            'canManagePatterns' => $canManagePatterns,
            'patterns' => $allPatterns,
            'patternHistory' => $patternHistory,
            'pattern' => $selected,
            'rows' => $this->rowsFor($selected),
            'dowValues' => CommutePatternService::DOW_VALUES,
            'defaultValidFrom' => $validFrom,
            'defaultValidTo' => old('valid_to', optional($selected?->valid_to)->toDateString() ?: $this->defaultValidTo(Carbon::parse($validFrom))),
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'pattern_id' => ['nullable', 'integer', 'exists:commute_patterns,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'closest_station' => ['required', 'string', 'max:255'],
            'train_line' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'reason' => ['nullable', 'string'],

            'rows' => ['required', 'array', 'min:1'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.dow' => ['required', Rule::in(DayOfWeek::values())],
            'rows.*.seq' => ['nullable', 'integer', 'min:0'],
            'rows.*.station_from' => ['nullable', 'string', 'max:255'],
            'rows.*.station_to' => ['nullable', 'string', 'max:255'],
            'rows.*.note' => ['nullable', 'string'],
            'rows.*.cost' => ['required', 'integer', 'min:0'],
            'rows.*.trip_type' => ['required', Rule::in(ExpenseTripType::values())],
        ]);
    }

    private function resolveTargetUser(Request $request, ?int $userId): User
    {
        if (! $userId || (int) $request->user()->id === (int) $userId) {
            return $request->user();
        }

        return User::findOrFail($userId);
    }

    private function rowsFor(?CommutePattern $pattern): array
    {
        if (! $pattern) {
            return collect(CommutePatternService::DOW_VALUES)
                ->map(fn (string $dow) => [
                    'id' => null,
                    'dow' => $dow,
                    'seq' => 100,
                    'station_from' => null,
                    'station_to' => null,
                    'note' => null,
                    'cost' => 0,
                    'trip_type' => ExpenseTripType::ROUND_TRIP->value,
                ])
                ->all();
        }

        return $pattern->legs
            ->map(fn ($leg) => [
                'id' => $leg->id,
                'dow' => is_object($leg->dow) ? $leg->dow->value : $leg->dow,
                'seq' => (int) $leg->seq,
                'station_from' => $leg->station_from,
                'station_to' => $leg->station_to,
                'note' => $leg->note,
                'cost' => (int) $leg->cost,
                'trip_type' => is_object($leg->trip_type) ? $leg->trip_type->value : $leg->trip_type,
            ])
            ->values()
            ->all();
    }

    private function defaultValidTo(Carbon $validFrom): string
    {
        $year = $validFrom->month <= 3 ? $validFrom->year : $validFrom->year + 1;

        return Carbon::create($year, 3, 31, 0, 0, 0, 'Asia/Tokyo')->toDateString();
    }

    private function routeFor(Request $request, User $target, ?CommutePattern $pattern, bool $new = false): string
    {
        $query = $new ? ['new' => 1] : [];
        if ($pattern) {
            $query['pattern'] = $pattern->id;
        }

        if ($request->user()->is($target)) {
            return route('expenses.pattern', $query);
        }

        return route('expenses.admin.pattern', array_merge(['user' => $target], $query));
    }
}
