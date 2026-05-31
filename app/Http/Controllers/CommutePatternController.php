<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseTripType;
use App\Http\Requests\CommutePattern\SaveCommutePatternRequest;
use App\Models\CommutePattern;
use App\Models\User;
use App\Services\CommutingExpenses\CommutePatternService;
use App\Services\CommutingExpenses\CommutePatternWriteService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function save(SaveCommutePatternRequest $request, CommutePatternWriteService $writer): JsonResponse
    {
        $data = $request->validated();
        $target = $this->resolveTargetUser($request, $data['user_id'] ?? null);

        $this->authorize('update', $target);

        $pattern = null;
        if (! empty($data['pattern_id'])) {
            $pattern = CommutePattern::query()
                ->where('user_id', $target->id)
                ->findOrFail($data['pattern_id']);
            $this->authorize('update', $pattern);
        }

        $pattern = $writer->save($target, $data, $pattern);

        return response()->json([
            'ok' => true,
            'pattern_id' => $pattern->id,
            'redirect_url' => $this->routeFor($request, $target, $pattern),
        ]);
    }

    public function destroy(Request $request, CommutePattern $pattern): JsonResponse
    {
        $target = $pattern->user()->firstOrFail();
        $this->authorize('delete', $pattern);

        $pattern->delete();

        return response()->json([
            'ok' => true,
            'redirect_url' => $this->routeFor($request, $target, null, true),
        ]);
    }

    private function renderFor(User $user, Request $request, bool $isAdminMode, CommutePatternService $patterns)
    {
        $canManagePatterns = $request->user()?->can('manage', CommutePattern::class) ?? false;
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
