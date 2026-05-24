<?php

namespace App\Http\Controllers;

use App\Models\CompanyClosure;
use App\Models\Holiday;
use App\Models\RestPattern;
use App\Models\RestPatternAdjustment;
use App\Models\RestPatternRule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarPatternEditorController extends Controller
{
    // ─── Page ───────────────────────────────────────────────────────────────

    public function index()
    {
        $patterns = RestPattern::orderBy('id')->get(['id', 'name', 'code']);
        return view('calendar.pattern_editor', compact('patterns'));
    }

    // ─── Events (for mini-calendar) ─────────────────────────────────────────

    public function events(Request $request)
    {
        $request->validate([
            'fiscal_year'      => 'required|integer|min:2000|max:2100',
            'rest_pattern_id'  => 'nullable|integer|exists:rest_patterns,id',
        ]);

        $fy    = (int) $request->query('fiscal_year');
        $start = Carbon::create($fy, 4, 1)->toDateString();
        $end   = Carbon::create($fy + 1, 3, 31)->toDateString();

        $result = [];

        // ── 祝日（holidays テーブル直接） ────────────────────────────────
        Holiday::whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->each(function ($h) use (&$result) {
                $result[] = [
                    'start'       => $h->date->toDateString(),
                    'title'       => $h->name,
                    '_type'       => 'holiday',
                    'is_observed' => (bool) $h->is_observed,
                ];
            });

        // ── 会社休暇（company_closures テーブル直接、期間を1日ずつ展開）────
        CompanyClosure::where('is_active', true)
            ->whereDate('end_date', '>=', $start)
            ->whereDate('start_date', '<=', $end)
            ->orderBy('start_date')
            ->each(function ($c) use ($start, $end, &$result) {
                $cur  = Carbon::parse(max($c->start_date->toDateString(), $start));
                $cEnd = Carbon::parse(min($c->end_date->toDateString(), $end));
                while ($cur->lte($cEnd)) {
                    $result[] = [
                        'start'  => $cur->toDateString(),
                        'title'  => $c->name,
                        '_type'  => 'closure',
                    ];
                    $cur->addDay();
                }
            });

        $patternId = $request->query('rest_pattern_id');
        if ($patternId) {
            // ── OffDay（rest_pattern_rules: 曜日ルール → LRD / ORD）────────
            $rules = RestPattern::with('rules')
                ->find($patternId)
                ?->rules
                ->keyBy('weekday') ?? collect();

            // ── Adjusting（rest_pattern_adjustments: 特定日の調整）────────
            $adjs = RestPatternAdjustment::where('rest_pattern_id', $patternId)
                ->where('is_active', true)
                ->whereBetween('date', [$start, $end])
                ->orderBy('date')
                ->get();

            // Adjusting がある日は曜日ルール（OffDay）をその日に適用しない
            $adjMap = $adjs->keyBy(fn($a) => $a->date->toDateString());

            $cur    = Carbon::parse($start);
            $endCar = Carbon::parse($end);
            while ($cur->lte($endCar)) {
                $ymd  = $cur->toDateString();
                $rule = $rules->get($cur->dayOfWeek);
                if ($rule && $rule->kind !== 'work' && !isset($adjMap[$ymd])) {
                    $result[] = [
                        'start' => $ymd,
                        'title' => $rule->kind === 'statutory_off' ? 'LRD' : 'ORD',
                        'kind'  => $rule->kind,
                        '_type' => 'offday',
                    ];
                }
                $cur->addDay();
            }

            foreach ($adjs as $a) {
                $result[] = [
                    'id'    => $a->id,
                    'start' => $a->date->toDateString(),
                    'kind'  => $a->kind,
                    'title' => $a->title ?: ($a->kind === 'add_off' ? 'ORD' : 'RWD'),
                    '_type' => 'adjusting',
                ];
            }
        }

        return response()->json($result);
    }

    // ─── PatternRule (LRD/ORD weekday rules) ────────────────────────────────

    public function patternRuleIndex(Request $request)
    {
        $request->validate([
            'rest_pattern_id' => 'required|integer|exists:rest_patterns,id',
        ]);
        $pid = (int) $request->query('rest_pattern_id');
        $rows = RestPatternRule::where('rest_pattern_id', $pid)
            ->orderBy('weekday')
            ->get(['weekday', 'kind']);

        return response()->json($rows->keyBy('weekday')->map(fn($r) => $r->kind));
    }

    public function patternRuleBulkUpdate(Request $request)
    {
        $data = $request->validate([
            'rest_pattern_id' => 'required|integer|exists:rest_patterns,id',
            'rules'           => 'required|array',
            'rules.*'         => 'required|in:statutory_off,prescribed_off,work',
        ]);
        $pid = $data['rest_pattern_id'];
        foreach ($data['rules'] as $weekday => $kind) {
            RestPatternRule::updateOrCreate(
                ['rest_pattern_id' => $pid, 'weekday' => (int) $weekday],
                ['kind' => $kind]
            );
        }
        return response()->json(['ok' => true]);
    }

    // ─── Holiday CRUD ────────────────────────────────────────────────────────

    public function holidayIndex(Request $request)
    {
        $request->validate(['fiscal_year' => 'required|integer']);
        $fy    = (int) $request->query('fiscal_year');
        $start = Carbon::create($fy, 4, 1)->toDateString();
        $end   = Carbon::create($fy + 1, 3, 31)->toDateString();

        $rows = Holiday::whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get(['id', 'date', 'name', 'name_en', 'is_observed', 'source']);

        return response()->json($rows->map(fn($h) => [
            'id'          => $h->id,
            'date'        => $h->date->toDateString(),
            'name'        => $h->name,
            'name_en'     => $h->name_en,
            'is_observed' => $h->is_observed,
            'source'      => $h->source,
        ]));
    }

    public function holidayStore(Request $request)
    {
        $data = $request->validate([
            'date'        => 'required|date|unique:holidays,date',
            'name'        => 'required|string|max:100',
            'name_en'     => 'nullable|string|max:100',
            'is_observed' => 'boolean',
            'source'      => 'nullable|string|max:255',
        ]);
        $data['country_code'] = 'JP';
        $data['year']         = Carbon::parse($data['date'])->year;
        $row = Holiday::create($data);

        return response()->json([
            'id'          => $row->id,
            'date'        => $row->date->toDateString(),
            'name'        => $row->name,
            'name_en'     => $row->name_en,
            'is_observed' => $row->is_observed,
            'source'      => $row->source,
        ], 201);
    }

    public function holidayUpdate(Request $request, Holiday $holiday)
    {
        $data = $request->validate([
            'date'        => 'required|date|unique:holidays,date,' . $holiday->id,
            'name'        => 'required|string|max:100',
            'name_en'     => 'nullable|string|max:100',
            'is_observed' => 'boolean',
            'source'      => 'nullable|string|max:255',
        ]);
        $data['year'] = Carbon::parse($data['date'])->year;
        $holiday->update($data);

        return response()->json([
            'id'          => $holiday->id,
            'date'        => $holiday->date->toDateString(),
            'name'        => $holiday->name,
            'name_en'     => $holiday->name_en,
            'is_observed' => $holiday->is_observed,
            'source'      => $holiday->source,
        ]);
    }

    public function holidayDestroy(Holiday $holiday)
    {
        $holiday->delete();
        return response()->json(['ok' => true]);
    }

    // ─── Adjusting CRUD ──────────────────────────────────────────────────────

    public function adjustingIndex(Request $request)
    {
        $request->validate([
            'fiscal_year'     => 'required|integer',
            'rest_pattern_id' => 'required|integer|exists:rest_patterns,id',
        ]);
        $fy    = (int) $request->query('fiscal_year');
        $pid   = (int) $request->query('rest_pattern_id');
        $start = Carbon::create($fy, 4, 1)->toDateString();
        $end   = Carbon::create($fy + 1, 3, 31)->toDateString();

        $rows = RestPatternAdjustment::where('rest_pattern_id', $pid)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();

        return response()->json($rows->map(fn($a) => [
            'id'               => $a->id,
            'rest_pattern_id'  => $a->rest_pattern_id,
            'date'             => $a->date->toDateString(),
            'kind'             => $a->kind,
            'title'            => $a->title,
            'is_active'        => $a->is_active,
            'note'             => $a->note,
        ]));
    }

    public function adjustingStore(Request $request)
    {
        $data = $request->validate([
            'rest_pattern_id' => 'required|integer|exists:rest_patterns,id',
            'date'            => 'required|date',
            'kind'            => 'required|in:add_off,work_instead',
            'title'           => 'nullable|string|max:100',
            'is_active'       => 'boolean',
            'note'            => 'nullable|string|max:500',
        ]);
        $data['code'] = $data['kind'] === 'add_off' ? 'ORD' : 'RWD';

        $row = RestPatternAdjustment::create($data);

        return response()->json([
            'id'              => $row->id,
            'rest_pattern_id' => $row->rest_pattern_id,
            'date'            => $row->date->toDateString(),
            'kind'            => $row->kind,
            'title'           => $row->title,
            'is_active'       => $row->is_active,
            'note'            => $row->note,
        ], 201);
    }

    public function adjustingUpdate(Request $request, RestPatternAdjustment $adjustment)
    {
        $data = $request->validate([
            'rest_pattern_id' => 'required|integer|exists:rest_patterns,id',
            'date'            => 'required|date',
            'kind'            => 'required|in:add_off,work_instead',
            'title'           => 'nullable|string|max:100',
            'is_active'       => 'boolean',
            'note'            => 'nullable|string|max:500',
        ]);
        $data['code'] = $data['kind'] === 'add_off' ? 'ORD' : 'RWD';
        $adjustment->update($data);

        return response()->json([
            'id'              => $adjustment->id,
            'rest_pattern_id' => $adjustment->rest_pattern_id,
            'date'            => $adjustment->date->toDateString(),
            'kind'            => $adjustment->kind,
            'title'           => $adjustment->title,
            'is_active'       => $adjustment->is_active,
            'note'            => $adjustment->note,
        ]);
    }

    public function adjustingDestroy(RestPatternAdjustment $adjustment)
    {
        $adjustment->delete();
        return response()->json(['ok' => true]);
    }

    // ─── Closure CRUD ────────────────────────────────────────────────────────

    public function closureIndex(Request $request)
    {
        $request->validate(['fiscal_year' => 'required|integer']);
        $fy    = (int) $request->query('fiscal_year');
        $start = Carbon::create($fy, 4, 1)->toDateString();
        $end   = Carbon::create($fy + 1, 3, 31)->toDateString();

        $rows = CompanyClosure::whereDate('end_date', '>=', $start)
            ->whereDate('start_date', '<=', $end)
            ->orderBy('start_date')
            ->get();

        return response()->json($rows->map(fn($c) => [
            'id'         => $c->id,
            'name'       => $c->name,
            'code'       => $c->code,
            'start_date' => $c->start_date->toDateString(),
            'end_date'   => $c->end_date->toDateString(),
            'is_active'  => $c->is_active,
            'note'       => $c->note,
        ]));
    }

    public function closureStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'code'       => 'required|string|max:20',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'is_active'  => 'boolean',
            'note'       => 'nullable|string|max:500',
        ]);
        $row = CompanyClosure::create($data);

        return response()->json([
            'id'         => $row->id,
            'name'       => $row->name,
            'code'       => $row->code,
            'start_date' => $row->start_date->toDateString(),
            'end_date'   => $row->end_date->toDateString(),
            'is_active'  => $row->is_active,
            'note'       => $row->note,
        ], 201);
    }

    public function closureUpdate(Request $request, CompanyClosure $closure)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'code'       => 'required|string|max:20',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'is_active'  => 'boolean',
            'note'       => 'nullable|string|max:500',
        ]);
        $closure->update($data);

        return response()->json([
            'id'         => $closure->id,
            'name'       => $closure->name,
            'code'       => $closure->code,
            'start_date' => $closure->start_date->toDateString(),
            'end_date'   => $closure->end_date->toDateString(),
            'is_active'  => $closure->is_active,
            'note'       => $closure->note,
        ]);
    }

    public function closureDestroy(CompanyClosure $closure)
    {
        $closure->delete();
        return response()->json(['ok' => true]);
    }
}
