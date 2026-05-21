<?php

namespace App\Http\Controllers;

use App\Models\ScheduleLine;
use App\Models\User;
use App\Services\CurrentScopeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ScheduleLineController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    public function edit(Request $request)
    {
        // フィルタ
        $activeOn = $request->has('active_on')
            ? $request->input('active_on') : now()->toDateString();
        $activeUntil = $request->input('active_until');

        $userIdRaw = $request->input('user_id', '');

        $fDow        = $request->filled('dow') ? (int)$request->input('dow') : null;
        $fSchoolName = trim((string)$request->input('school_name', ''));

        $refLineId = $request->filled('schedule_line_id')
            ? (int)$request->input('schedule_line_id')
            : null;

        // ScheduleLine 本体 + 関連ロード
        $linesQuery = ScheduleLine::query()
            ->with([
                'user:id,first_name,family_name,employee_code',
                'details' => function ($q) {
                    $q->with([
                        'lesson:id,lesson_name,lesson_code,lesson_minute,lesson_type',
                    ])->orderBy('start_time');
                },
            ])
            ->orderBy('dow')
            ->orderBy('school_name')
            ->orderBy('effective_start');

        // スコープ: schedule_lines.district_id / department_id で絞る
        $linesQuery->where('district_id', $this->scopeService->currentDistrictId())
                   ->where('department_id', $this->scopeService->currentDepartmentId());

        // ▼ user_id フィルタ（'null' は未割当のみ、数値はそのID、空はすべて）
        if ($userIdRaw === 'null') {
            $linesQuery->whereNull('user_id');
        } elseif (is_numeric($userIdRaw) && $userIdRaw !== '') {
            $linesQuery->where('user_id', (int)$userIdRaw);
        }

        if (!is_null($fDow)) {
            $linesQuery->where('dow', $fDow);
        }
        if ($fSchoolName !== '') {
            $linesQuery->whereLikeInsensitive('school_name', $fSchoolName);
        }

        // ★ 関連検索用（このIDの前後＝親子系統を表示）
        if ($refLineId) {
            $seed = ScheduleLine::query()->select(['id', 'parent_line_id'])->find($refLineId);

            if ($seed) {
                $relatedIds = [];

                $cur = $seed;
                while ($cur && $cur->parent_line_id) {
                    $relatedIds[] = $cur->parent_line_id;
                    $cur = ScheduleLine::query()->select(['id', 'parent_line_id'])->find($cur->parent_line_id);
                }

                $frontier = [$seed->id];
                $visited  = [$seed->id => true];
                do {
                    $children = ScheduleLine::query()->select(['id', 'parent_line_id'])
                        ->whereIn('parent_line_id', $frontier)
                        ->get();

                    $next = [];
                    foreach ($children as $ch) {
                        if (!isset($visited[$ch->id])) {
                            $visited[$ch->id] = true;
                            $relatedIds[] = $ch->id;
                            $next[] = $ch->id;
                        }
                    }
                    $frontier = $next;
                } while (!empty($frontier));

                $relatedIds[] = $seed->id;
                $relatedIds = array_values(array_unique($relatedIds));

                $linesQuery->whereIn('id', $relatedIds);
            } else {
                $linesQuery->whereRaw('1=0');
            }
        } else {
            if (!empty($activeOn)) {
                $periodStart = \Carbon\Carbon::parse($activeOn)->toDateString();
                $periodEnd   = $activeUntil ? \Carbon\Carbon::parse($activeUntil)->toDateString() : $periodStart;

                $linesQuery
                    ->whereDate('effective_start', '<=', $periodEnd)
                    ->where(function ($q) use ($periodStart) {
                        $q->whereDate('effective_end', '>=', $periodStart)
                            ->orWhereNull('effective_end');
                    });
            }
        }

        $lines = $linesQuery->paginate(50);

        // ユーザー選択肢（スコープ内のユーザー一覧）
        $userOptions = $this->scopeService->targetUserQuery()
            ->orderBy('family_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'family_name', 'employee_code'])
            ->map(function ($u) {
                $label = trim($u->family_name . ' ' . ($u->first_name ?? ''));
                if (!empty($u->employee_code)) {
                    $label .= " [{$u->employee_code}]";
                }
                return ['id' => $u->id, 'label' => $label];
            })
            ->values();

        // details を「期間の変化点」で区切った時間系列へ整形
        $seriesByLine = [];
        foreach ($lines as $line) {
            $seriesByLine[$line->id] = $this->buildTimeSeries($line->details);
        }

        $dowOptions = [
            0 => '日', 1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土',
        ];

        return view('schedule.lineEdit', [
            'lines'       => $lines,
            'dowOptions'  => $dowOptions,
            'userOptions' => $userOptions,
            'activeOn'    => $activeOn,
            'userId'      => $userIdRaw,
            'seriesByLine' => $seriesByLine,
        ]);
    }

    public function update(Request $request, ScheduleLine $line)
    {
        $data = $request->validate([
            'user_id'         => ['nullable', 'exists:users,id'],
            'dow'             => ['required', 'integer', Rule::in([0, 1, 2, 3, 4, 5, 6])],
            'school_name'     => ['required', 'string', 'max:255'],
            'start_time'      => ['required', 'date_format:H:i'],
            'end_time'        => ['required', 'date_format:H:i', function ($attr, $val, $fail) use ($request) {
                if ($request->input('start_time') && $val <= $request->input('start_time')) {
                    $fail('end_time は start_time より後である必要があります。');
                }
            }],
            'effective_start' => ['required', 'date'],
            'effective_end'   => ['required', 'date', function ($attr, $val, $fail) use ($request) {
                if ($request->input('effective_start') && $val < $request->input('effective_start')) {
                    $fail('effective_end は effective_start 以降である必要があります。');
                }
            }],
            'handover_memo'   => ['nullable', 'string', 'max:2000'],
        ]);

        $line->fill($data)->save();

        return back()->with('status', "Line #{$line->id} を更新しました。");
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return response()->json(['ok' => false, 'message' => 'No items to update.'], 422);
        }

        $errors = [];
        $updated = 0;

        DB::beginTransaction();
        try {
            foreach ($items as $raw) {
                $lineId = $raw['id'] ?? null;
                if (!$lineId) {
                    $errors[] = ['id' => null, 'messages' => ['Row ID is missing.']];
                    continue;
                }

                $line = ScheduleLine::find($lineId);
                if (!$line) {
                    $errors[] = ['id' => $lineId, 'messages' => ['Line not found.']];
                    continue;
                }

                $v = Validator::make($raw, [
                    'user_id'         => ['nullable', 'exists:users,id'],
                    'dow'             => ['required', 'integer', Rule::in([0, 1, 2, 3, 4, 5, 6])],
                    'school_name'     => ['required', 'string', 'max:255'],
                    'start_time'      => ['required', 'date_format:H:i'],
                    'end_time'        => ['required', 'date_format:H:i', function ($attr, $val, $fail) use ($raw) {
                        if (!empty($raw['start_time']) && $val <= $raw['start_time']) {
                            $fail('end_time は start_time より後である必要があります。');
                        }
                    }],
                    'effective_start' => ['required', 'date'],
                    'effective_end'   => ['required', 'date', function ($attr, $val, $fail) use ($raw) {
                        if (!empty($raw['effective_start']) && $val < $raw['effective_start']) {
                            $fail('effective_end は effective_start 以降である必要があります。');
                        }
                    }],
                    'handover_memo'   => ['nullable', 'string', 'max:2000'],
                ]);

                if ($v->fails()) {
                    $errors[] = ['id' => $lineId, 'messages' => $v->errors()->all()];
                    continue;
                }

                $data = $v->validated();
                $data['start_time'] = $data['start_time'] . ':00';
                $data['end_time']   = $data['end_time']   . ':00';

                $line->fill($data)->save();
                $updated++;
            }

            DB::commit();

            $msg = "Saved {$updated} line(s).";
            if (!empty($errors)) $msg .= " " . count($errors) . " error(s) occurred.";

            return response()->json([
                'ok'      => empty($errors),
                'message' => $msg,
                'updated' => $updated,
                'errors'  => $errors,
            ], empty($errors) ? 200 : 207);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'ok'      => false,
                'message' => 'Failed to save lines.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'       => ['nullable', 'exists:users,id'],
            'school_name'   => ['nullable', 'string', 'max:255'],
            'dow'           => ['nullable', 'integer', 'between:0,6'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $line = new ScheduleLine();
        $line->user_id         = $data['user_id'] ?? null;
        $line->total_minutes   = 0;
        $line->dow             = $data['dow'] ?? 0;
        $line->school_name     = $data['school_name'] ?? '';
        $line->start_time      = '00:00:00';
        $line->end_time        = '00:00:00';
        $line->effective_start = now()->toDateString();
        $line->effective_end   = now()->addMonths(1)->toDateString();
        $line->handover_memo   = null;
        $line->district_id     = $this->scopeService->currentDistrictId();
        $line->department_id   = $data['department_id'] ?? $this->scopeService->currentDepartmentId();
        $line->save();

        return response()->json([
            'ok'      => true,
            'message' => "Line #{$line->id} added.",
            'line_id' => $line->id,
        ]);
    }

    public function destroy(Request $request, ScheduleLine $line): JsonResponse
    {
        try {
            if (method_exists($line, 'details')) {
                $line->details()->delete();
            }

            $id = $line->id;
            $line->delete();

            return response()->json([
                'ok'      => true,
                'message' => "Line #{$id} deleted.",
                'id'      => $id,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok'      => false,
                'message' => 'Failed to delete. Please check related data or constraints.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    public function copy(Request $request, ScheduleLine $line): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'effective_start' => ['required', 'date'],
            'effective_end'   => ['required', 'date', 'after_or_equal:effective_start'],
            'user_id'         => ['nullable', 'exists:users,id'],
            'handover_memo'   => ['nullable', 'string', 'max:2000'],
        ]);

        $newStart = \Carbon\Carbon::parse($data['effective_start'])->startOfDay();
        $newEnd   = \Carbon\Carbon::parse($data['effective_end'])->startOfDay();
        $targetUserId = $data['user_id'] ?? null;

        DB::beginTransaction();
        try {
            // 1) 元行クローズ
            $lineStart = \Carbon\Carbon::parse($line->effective_start)->startOfDay();
            $lineEnd   = $line->effective_end ? \Carbon\Carbon::parse($line->effective_end)->startOfDay() : null;

            if ($newStart->gt($lineStart) && (!$lineEnd || $lineEnd->gte($newStart))) {
                $line->effective_end = $newStart->copy()->subDay()->toDateString();
                $line->save();
            }

            // 2) 重複チェック
            $sameAttrs = \App\Models\ScheduleLine::query()
                ->when(
                    is_null($targetUserId),
                    fn($q) => $q->whereNull('user_id'),
                    fn($q) => $q->where('user_id', $targetUserId)
                )
                ->where('dow', $line->dow)
                ->whereEqualsInsensitive('school_name', $line->school_name)
                ->where('start_time', $line->start_time)
                ->where('end_time', $line->end_time)
                ->where(function ($q) use ($newStart, $newEnd) {
                    $q->whereDate('effective_start', '<=', $newEnd)
                        ->where(function ($qq) use ($newStart) {
                            $qq->whereDate('effective_end', '>=', $newStart)
                                ->orWhereNull('effective_end');
                        });
                })
                ->exists();

            if ($sameAttrs) {
                DB::rollBack();
                return response()->json([
                    'ok' => false,
                    'message' => '同一内容の行が指定期間に既に存在します（重複回避ルール）。'
                ], 422);
            }

            // 3) 新規作成
            $created = $line->replicate(['effective_start', 'effective_end', 'created_at', 'updated_at', 'id']);
            $created->user_id         = $targetUserId;
            $created->effective_start = $newStart->toDateString();
            $created->effective_end   = $newEnd->toDateString();
            $created->parent_line_id  = $line->id;
            $created->handover_memo   = $data['handover_memo'] ?? null;
            $created->save();

            // 4) details クリップ複写
            $line->loadMissing(['details']);
            foreach ($line->details as $d) {
                $dStart = \Carbon\Carbon::parse($d->effective_start)->startOfDay();
                $dEnd   = $d->effective_end ? \Carbon\Carbon::parse($d->effective_end)->startOfDay() : null;

                $overlapStart = $dStart->max($newStart);
                $overlapEnd   = $dEnd ? $dEnd->min($newEnd) : $newEnd;

                if ($overlapStart->gt($overlapEnd)) continue;

                $newDetail = $d->replicate(['id', 'created_at', 'updated_at']);
                $newDetail->schedule_line_id = $created->id;
                $newDetail->effective_start  = $overlapStart->toDateString();
                $newDetail->effective_end    = $overlapEnd ? $overlapEnd->toDateString() : null;
                $newDetail->save();
            }

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => "Line #{$line->id} を複写して新規 Line #{$created->id} を作成しました（user: " . ($targetUserId ?? 'NULL') . "）。",
                'new_id' => $created->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'ok' => false,
                'message' => '複写に失敗しました。入力期間や関連データをご確認ください。',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function buildTimeSeries(Collection $details): array
    {
        if ($details->isEmpty()) {
            return [];
        }

        $points = collect();
        $maxEnd = null;
        $hasOpenEnd = false;

        foreach ($details as $d) {
            $s = Carbon::parse($d->effective_start)->startOfDay();
            $points->push($s->copy());

            if ($d->effective_end) {
                $e = Carbon::parse($d->effective_end)->startOfDay();
                $points->push($e->copy()->addDay());
                $maxEnd = $maxEnd ? $e->max($maxEnd) : $e;
            } else {
                $hasOpenEnd = true;
            }
        }

        $points = $points
            ->unique(fn($c) => $c->toDateString())
            ->sort()
            ->values();

        if ($points->isEmpty()) {
            return [];
        }

        $segments = [];
        for ($i = 0; $i < $points->count(); $i++) {
            $start = $points[$i]->copy();
            if ($i + 1 < $points->count()) {
                $end = $points[$i + 1]->copy()->subDay();
            } else {
                $end = $hasOpenEnd ? null : $maxEnd;
            }

            $active = $details->filter(function ($d) use ($start, $end) {
                $ds = Carbon::parse($d->effective_start)->startOfDay();
                $de = $d->effective_end ? Carbon::parse($d->effective_end)->startOfDay() : null;

                $leftOk  = $de ? $de >= $start : true;
                $rightOk = $end ? $ds <= $end : true;
                return $leftOk && $rightOk;
            });

            if ($active->isEmpty()) {
                continue;
            }

            $items = $active->sortBy(function ($d) {
                return $d->start_time ?? '99:99:99';
            })
                ->map(function ($d) {
                    $raw = $d->start_time;
                    $startStr = null;

                    if ($raw !== null) {
                        $s = trim((string)$raw);
                        $s = str_replace('：', ':', $s);

                        if (preg_match('/^\d{1,2}:\d{2}$/', $s)) {
                            $startStr = $s;
                        } elseif (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $s)) {
                            $startStr = substr($s, 0, 5);
                        } elseif (preg_match('/^\d{3,4}$/', $s)) {
                            $s = str_pad($s, 4, '0', STR_PAD_LEFT);
                            $startStr = substr($s, 0, 2) . ':' . substr($s, 2, 2);
                        } else {
                            try {
                                $startStr = \Carbon\Carbon::parse($s)->format('H:i');
                            } catch (\Throwable $e) {
                                $startStr = null;
                            }
                        }
                    }

                    $lesson = $d->lesson;
                    $minute = $lesson->lesson_minute ?? null;
                    $minute = is_numeric($minute) ? (int)$minute : null;

                    $endStr = null;
                    if ($startStr && $minute !== null) {
                        $endStr = \Carbon\Carbon::createFromFormat('H:i', $startStr)
                            ->addMinutes($minute)
                            ->format('H:i');
                    }

                    return [
                        'name'   => $lesson->lesson_name ?? null,
                        'code'   => $lesson->lesson_code ?? null,
                        'minute' => $minute,
                        'start'  => $startStr,
                        'end'    => $endStr,
                    ];
                })
                ->values()
                ->all();

            $segments[] = [
                'start' => $start,
                'end'   => $end,
                'items' => $items,
            ];
        }

        return $segments;
    }
}
