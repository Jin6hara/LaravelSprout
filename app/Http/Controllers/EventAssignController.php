<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\ShiftType;
use App\Http\Requests\EventAssign\BulkUpdateEventRequest;
use App\Http\Requests\EventAssign\CopyEventRequest;
use App\Http\Requests\EventAssign\UpdateEventRequest;
use App\Models\Event;
use App\Models\User;
use App\Services\CurrentScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

use App\Services\Calendar\Providers\SubCountProvider;

class EventAssignController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    /**
     * イベント管理画面（検索・一覧）
     * GET /calendar/edit — 日付・ユーザー・学校名などの条件でイベントを検索し 24 件ずつページネーション表示
     */
    public function edit(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $userOptions = $this->scopeService->targetUserQuery()
            ->select('id', 'first_name', 'family_name', 'employee_code')
            ->orderBy('employee_code')
            ->limit(500)
            ->get();

        // ★追加：School名の候補（重複除去＆名前順）
        $schoolNames = $this->scopeService->schoolQuery()
            ->where('is_active', true)
            ->orderBy('school_name')
            ->distinct()
            ->pluck('school_name');

        // モーダルからevent_id を受け取る
        $eventId        = $request->input('event_id');

        // 検索パラメータ取得
        $originalUserId = $request->input('original_user_id');
        $assignedUserId = $request->input('assigned_user_id');
        $status         = $request->input('status');
        $type           = $request->input('type');

        $leaveType      = $request->input('Leave_type');
        $schoolName     = $request->input('school_name');
        $title          = $request->input('title');
        $lesson         = $request->input('Lesson');

        // 期間検索（from/to）。どちらか一方だけでもOK
        $eventDateFrom  = $request->input('event_date');
        $eventDateTo    = $request->input('end_date');

        // 1. end_day のみ指定 → エラー
        if ($eventDateTo && !$eventDateFrom) {
            return back()
                ->withErrors(['event_date' => '対象日を入力してください。'])
                ->withInput();
        }
        // 2. start_day > end_day → エラー
        if ($eventDateFrom && $eventDateTo && $eventDateFrom > $eventDateTo) {
            return back()
                ->withErrors(['event_date' => '開始日は終了日より前の日付を指定してください。'])
                ->withInput();
        }

        $events = Event::query()
            ->where('district_id', $this->scopeService->currentDistrictId())
            ->where('department_id', $this->scopeService->currentDepartmentId())

            // モーダルからevent_id が来ていれば最優先で絞り込み（ID一致のみ）
            ->when($eventId, fn($q) => $q->whereKey($eventId))

            ->with(['assignedUser:id,name,employee_code', 'originalUser:id,name,employee_code'])
            ->when($originalUserId, fn($q) => $q->where('original_user_id', $originalUserId))
            ->when($assignedUserId, fn($q) => $q->where('assigned_user_id', $assignedUserId))
            ->when($status,         fn($q) => $q->where('status', $status))
            ->when($type,           fn($q) => $q->where('type', $type))
            // ⬇︎ 日付フィルタ（優先順位：1) from+to 期間 2) fromのみ 単日
            ->when($eventDateFrom && $eventDateTo, function ($q) use ($eventDateFrom, $eventDateTo) {
                $q->whereBetween('event_date', [$eventDateFrom, $eventDateTo]);
            })
            ->when($eventDateFrom && !$eventDateTo, function ($q) use ($eventDateFrom) {
                $q->whereDate('event_date', $eventDateFrom);
            })
            ->when(
                $leaveType !== null && $leaveType !== '',
                fn($q) =>
                $q->whereLikeInsensitive('Leave_type', $leaveType)
            )
            ->when(
                $schoolName !== null && $schoolName !== '',
                fn($q) =>
                $q->whereLikeInsensitive('school_name', $schoolName)
            )
            ->when(
                $title !== null && $title !== '',
                fn($q) =>
                $q->whereLikeInsensitive('title', $title)
            )
            ->when(
                $lesson !== null && $lesson !== '',
                fn($q) =>
                $q->whereLikeInsensitive('Lesson', $lesson)
            )
            ->orderByDesc('event_date')
            ->orderBy('school_name')
            ->orderBy('start_time') // 効かない：15時が10時より前に来る。FullCalendarも同じ問題。
            ->orderBy('assigned_user_id')
            ->paginate(24)
            ->withQueryString();

        $statusOptions = [
            EventStatus::Filled->value => EventStatus::Filled->label(),
            EventStatus::Fixed->value => EventStatus::Fixed->label(),
            EventStatus::Pending->value => EventStatus::Pending->label(),
            EventStatus::InProcess->value => EventStatus::InProcess->label(),
        ];
        $typeOptions = [
            ShiftType::RegularTime->value => ShiftType::RegularTime->short(),
            ShiftType::Overtime->value => ShiftType::Overtime->short(),
            ShiftType::ScheduleChange->value => ShiftType::ScheduleChange->short(),
            ShiftType::Special->value => ShiftType::Special->short(),
            ShiftType::RosteredWorkingDay->value => ShiftType::RosteredWorkingDay->short(),
            ShiftType::NoneRequired->value => ShiftType::NoneRequired->short(),
        ];

        // $schoolNames を view に渡す
        return view('calendar.edit', compact('events', 'userOptions', 'statusOptions', 'typeOptions', 'schoolNames'));
    }

    /**
     * イベントを複製して新規作成
     * POST /calendar/events/copy — バリデーション済みデータを元に時刻を正規化し、同スコープで新規 Event を作成
     */
    public function copy(CopyEventRequest $request)
    {
        $this->authorize('create', Event::class);

        $validated = $request->validated();


        // ⬇︎ H:i → H:i:s に正規化（nullはそのまま）
        foreach (['start_time', 'end_time'] as $k) {
            if ($request->filled($k)) {
                $validated[$k] = Carbon::createFromFormat('H:i', $request->input($k))->format('H:i:s');
            } else {
                $validated[$k] = null;
            }
        }

        // ⬇︎ total_duration 未入力なら自動計算（Observer があれば最終整合はそちらでも取れます）
        if (!$request->filled('total_duration') && $validated['start_time'] && $validated['end_time']) {
            $start = Carbon::createFromFormat('H:i:s', $validated['start_time']);
            $end   = Carbon::createFromFormat('H:i:s', $validated['end_time']);
            if ($end->lt($start)) $end->addDay(); // 日跨ぎ
            $mins = $start->diffInMinutes($end);
            $validated['total_duration'] = sprintf('%d:%02d', intdiv($mins, 60), $mins % 60);
        }

        $this->authorizeReferencedUsers($validated);
        $validated['district_id']   = $this->scopeService->currentDistrictId();
        $validated['department_id'] = $this->scopeService->currentDepartmentId();
        Event::create($validated);

        return back()->with('toast', 'Shift copied.');
    }

    /**
     * 空の新規イベントを作成
     * POST /calendar/events — 指定日に pending・regular_time の空白イベントを生成して管理画面へ戻す
     */
    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        // リクエストやURLクエリから event_date を取得（POSTでもGETでも対応）
        $date = $request->input('event_date', now()->toDateString());

        // 空白イベントを作成
        $event = Event::create([
            'event_date'    => $date,
            'status'        => EventStatus::Pending->value,
            'type'          => ShiftType::RegularTime->value,
            'district_id'   => $this->scopeService->currentDistrictId(),
            'department_id' => $this->scopeService->currentDepartmentId(),
        ]);

        return back()->with('toast', "Created shift at {$date}.");
    }

    /**
     * イベントを1件更新
     * PATCH /calendar/events/{event} — 時刻を H:i:s 形式へ正規化し、total_duration を自動計算して保存
     */
    public function update(UpdateEventRequest $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validated();

        // ⬇︎ H:i または H:i:s を安全に H:i:s へ正規化（例外を出さない）
        $normalizeTime = function ($val) {
            if ($val === null || $val === '') return null;
            $val = trim($val);
            if (preg_match('/^\d{2}:\d{2}$/', $val)) {
                return $val . ':00';
            }
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $val)) {
                return $val;
            }
            // ここに来ない想定（上の regex バリデーションで弾かれる）
            return null;
        };

        $validated['start_time'] = $normalizeTime($request->input('start_time', ''));
        $validated['end_time']   = $normalizeTime($request->input('end_time', ''));
        $this->authorizeReferencedUsers($validated);

        // ⬇︎ total_duration 未入力で start/end がある場合は自動計算
        if (empty($validated['total_duration']) && $validated['start_time'] && $validated['end_time']) {
            // ここは単純な文字列演算でOK
            [$sh, $sm] = array_map('intval', explode(':', substr($validated['start_time'], 0, 5)));
            [$eh, $em] = array_map('intval', explode(':', substr($validated['end_time'],   0, 5)));
            $s = $sh * 60 + $sm;
            $e = $eh * 60 + $em;
            $diff = $e - $s;
            if ($diff < 0) $diff += 1440; // 日跨ぎ
            $validated['total_duration'] = floor($diff / 60) . ':' . str_pad($diff % 60, 2, '0', STR_PAD_LEFT);
        }

        $event->fill($validated)->save();

        // ✅ フラッシュバナーではなくトースト用のキーへ
        return back()->with('toast', 'Updated.');
    }

    /**
     * イベントを一括更新（JSON API）
     * POST /calendar/events/bulk-update — items 配列を1件ずつバリデーション・保存し、成功/失敗件数を JSON で返す
     */
    public function bulkUpdate(BulkUpdateEventRequest $request)
    {
        $this->authorize('viewAny', Event::class);

        $items = $request->input('items', []);

        $results = [];
        foreach ($items as $row) {
            $v = Validator::make($row, BulkUpdateEventRequest::itemRules());
            if ($v->fails()) {
                $results[] = ['id' => $row['id'] ?? null, 'ok' => false, 'errors' => $v->errors()->toArray()];
                continue;
            }
            $data = $v->validated();
            $event = Event::find($data['id']);
            if (! $event) {
                $results[] = ['id' => $data['id'], 'ok' => false, 'errors' => ['Event not found.']];
                continue;
            }
            $this->authorize('update', $event);
            $this->authorizeReferencedUsers($data);

            // H:i → H:i:s に正規化
            foreach (['start_time', 'end_time'] as $k) {
                if (!empty($data[$k])) {
                    $data[$k] = Carbon::createFromFormat('H:i', $data[$k])->format('H:i:s');
                } else {
                    $data[$k] = null;
                }
            }

            // total_duration 未入力なら自動計算
            if (empty($data['total_duration']) && $data['start_time'] && $data['end_time']) {
                $start = Carbon::createFromFormat('H:i:s', $data['start_time']);
                $end   = Carbon::createFromFormat('H:i:s', $data['end_time']);
                if ($end->lt($start)) $end->addDay();
                $mins = $start->diffInMinutes($end);
                $data['total_duration'] = sprintf('%d:%02d', intdiv($mins, 60), $mins % 60);
            }

            $event->fill($data)->save(); // Observerがあれば最終整合を取ってくれます

            $results[] = ['id' => $event->id, 'ok' => true];
        }

        $okCount = collect($results)->where('ok', true)->count();
        $ngCount = count($results) - $okCount;

        // ✅ JSON返却でもフラッシュを仕込む
        $flash = $ngCount ? "一部保存に失敗しました（保存: {$okCount} / 失敗: {$ngCount}）" : "Updated {$okCount} shift(s).";
        session()->flash('toast', $flash);

        return response()->json([
            'ok'      => $ngCount === 0,
            'updated' => $okCount,
            'failed'  => $ngCount,
            'results' => $results,
            'message' => $ngCount ? '一部保存に失敗しました' : 'すべて保存しました',
        ]);
    }

    /**
     * イベントを削除
     * DELETE /calendar/events/{event} — 1件のイベントを削除してカレンダー管理画面へ戻す
     */
    public function destroy(Request $request, Event $event)
    {
        $this->authorize('delete', $event);
        $event->delete();
        return back()->with('toast', 'Deleted');
    }

    // ---- Vue プレビュー用 ----

    /**
     * サブリスト PDF プレビュー画面
     * GET /calendar/pdf/sublist/preview — Vue コンポーネントが PDF を描画するためのプレビュー用 Blade を返す
     */
    public function sublistPreview(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        return view('pdf.preview', [
            'type'        => 'sublist',
            'queryString' => $request->getQueryString() ?? '',
        ]);
    }

    /**
     * 確認書 PDF プレビュー画面
     * GET /calendar/pdf/confirmations/preview — Vue コンポーネントが ALP/OT 確認書 PDF を描画するためのプレビュー用 Blade を返す
     */
    public function confirmationsPreview(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        return view('pdf.preview', [
            'type'        => 'confirmations',
            'queryString' => $request->getQueryString() ?? '',
        ]);
    }

    /**
     * サブリスト PDF 用データを JSON で返す
     * GET /calendar/pdf/sublist/json — モード(tentative/final/master)・除外ID・検索条件に基づきイベントと代講者情報を整形して返す
     */
    public function exportSubPdfJson(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $mode = $request->string('mode')->lower()->value();
        if (!in_array($mode, ['tentative', 'final', 'master'], true)) {
            $mode = 'tentative';
        }

        $excludeEventIds = collect($request->input('exclude_event_ids', []))
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int)$v)
            ->unique()->values()->all();

        $query  = $this->baseEventQuery($request)
            ->when(!empty($excludeEventIds), fn($q) => $q->whereNotIn('id', $excludeEventIds));
        $events = $query->get();

        $subSummaryByDate = [];
        if ($events->isNotEmpty()) {
            $minDate  = Carbon::parse($events->min('event_date'))->startOfDay();
            $maxDate  = Carbon::parse($events->max('event_date'))->addDay()->startOfDay();
            $provider = app(SubCountProvider::class);
            $subEvents = $provider->provide($request->user(), $minDate, $maxDate);

            foreach ($subEvents as $ev) {
                $day = Carbon::parse($ev->start)->toDateString();
                $ext = $ev->extendedProps ?? [];
                $subSummaryByDate[$day] = [
                    'users'        => $ext['users']        ?? [],
                    'absent_users' => $ext['absent_users'] ?? [],
                ];
            }
        }

        $fmtTime = fn($t) => $t ? (is_string($t) ? substr($t, 0, 5) : optional($t)->format('H:i')) : '';
        $fmtDate = fn($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : substr((string)$d, 0, 10);

        $fmtSubText = function (array $buckets): string {
            $parts = [];
            foreach ($buckets as $items) {
                foreach ($items as $u) {
                    $label = $u['name'] ?? '';
                    $start = $u['start_hm'] ?? null;
                    $end   = $u['end_hm']   ?? null;
                    if ($start || $end) {
                        $label .= ' (' . trim(($start ?? '') . ' - ' . ($end ?? '')) . ')';
                    }
                    $parts[] = $label;
                }
            }
            return $parts ? implode(', ', $parts) : 'None';
        };

        $fmtSubCount = fn(array $buckets): int => array_sum(array_map('count', $buckets));

        $titleMap = ['tentative' => 'Tentative Sublist', 'final' => 'Final Sublist', 'master' => 'Master Sublist'];

        $grouped = $events->groupBy(fn($e) => $fmtDate($e->event_date) ?: 'No Date');

        $groups = $grouped->map(function ($rows, $date) use ($mode, $fmtTime, $subSummaryByDate, $fmtSubText, $fmtSubCount) {
            $sub          = $subSummaryByDate[$date] ?? null;
            $subSummary   = null;
            if ($sub) {
                $presentCount = $fmtSubCount($sub['users'] ?? []);
                $absentCount  = $fmtSubCount($sub['absent_users'] ?? []);
                $subSummary   = [
                    'present_count' => $presentCount,
                    'absent_count'  => $absentCount,
                    'present_text'  => $fmtSubText($sub['users'] ?? []),
                    'absent_text'   => $fmtSubText($sub['absent_users'] ?? []),
                ];
            }

            return [
                'date'        => $date,
                'rows'        => $rows->map(fn($e) => [
                    'title'          => $e->title,
                    'school_name'    => $e->school_name,
                    'original_user'  => optional($e->originalUser)->name,
                    'assigned_user'  => optional($e->assignedUser)->name,
                    'employee_code'  => optional($e->assignedUser)->employee_code,
                    'start_time'     => $fmtTime($e->start_time),
                    'end_time'       => $fmtTime($e->end_time),
                    'lesson'         => $e->Lesson,
                    'leave_type'     => $e->Leave_type,
                    'type_label'     => $e->type_label,
                    'status'         => $e->status,
                    'notes'          => $e->notes,
                ])->values(),
                'sub_summary' => $subSummary,
            ];
        })->values();

        return response()->json([
            'meta'   => [
                'mode'       => $mode,
                'range_from' => $request->input('event_date'),
                'range_to'   => $request->input('end_date'),
                'title'      => $titleMap[$mode],
            ],
            'groups' => $groups,
        ]);
    }

    /**
     * 確認書 PDF 用データを JSON で返す
     * GET /calendar/pdf/confirmations/json — モード(alp/ot)・除外IDで絞り込んだイベントを日付ごとにグループ化して返す
     */
    public function exportConfirmationsPdfJson(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $mode = $request->string('mode')->lower()->value();
        if (!in_array($mode, ['alp', 'ot'], true)) {
            $mode = 'alp';
        }

        $excludeEventIds = collect($request->input('exclude_event_ids', []))
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int)$v)
            ->unique()->values()->all();

        $query  = $this->baseEventQuery($request)
            ->when(!empty($excludeEventIds), fn($q) => $q->whereNotIn('id', $excludeEventIds));
        $events = $query->get();

        $fmtTime = fn($t) => $t ? (is_string($t) ? substr($t, 0, 5) : optional($t)->format('H:i')) : '';
        $fmtDate = fn($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : substr((string)$d, 0, 10);

        $grouped = $events->groupBy(fn($e) => $fmtDate($e->event_date) ?: 'No Date');

        $groups = $grouped->map(fn($rows, $date) => [
            'date' => $date,
            'rows' => $rows->map(fn($e) => [
                'school_name'   => $e->school_name,
                'original_user' => optional($e->originalUser)->name,
                'start_time'    => $fmtTime($e->start_time),
                'end_time'      => $fmtTime($e->end_time),
                'lesson'        => $e->Lesson,
                'leave_type'    => $e->Leave_type,
                'type_label'    => $e->type_label,
            ])->values(),
        ])->values();

        return response()->json([
            'meta'   => [
                'mode'       => $mode,
                'range_from' => $request->input('event_date'),
                'range_to'   => $request->input('end_date'),
                'title'      => $mode === 'ot' ? 'OT Confirmation List' : 'ALP Confirmation List',
            ],
            'groups' => $groups,
        ]);
    }

    /**
     * 共通イベントクエリビルダ
     * edit()・exportSubPdfJson()・exportConfirmationsPdfJson() で共有するフィルタ条件を組み立てて返す
     */
    protected function baseEventQuery(Request $request)
    {
        $eventId        = $request->input('event_id');
        $originalUserId = $request->input('original_user_id');
        $assignedUserId = $request->input('assigned_user_id');
        $status         = $request->input('status');
        $type           = $request->input('type');

        $leaveType      = $request->input('Leave_type');
        $schoolName     = $request->input('school_name');
        $title          = $request->input('title');
        $lesson         = $request->input('Lesson');

        $eventDateFrom  = $request->input('event_date');
        $eventDateTo    = $request->input('end_date');

        // edit/pdf 共通の簡易チェック（back redirect は edit 側のみのほうが自然なら削除OK）
        if ($eventDateTo && !$eventDateFrom) {
            return redirect()->back()
                ->withErrors(['event_date' => '対象日を入力してください。'])
                ->withInput();
        }
        if ($eventDateFrom && $eventDateTo && $eventDateFrom > $eventDateTo) {
            return redirect()->back()
                ->withErrors(['event_date' => '開始日は終了日より前の日付を指定してください。'])
                ->withInput();
        }

        return Event::query()
            ->where('district_id', $this->scopeService->currentDistrictId())
            ->where('department_id', $this->scopeService->currentDepartmentId())
            ->when($eventId, fn($q) => $q->whereKey($eventId))
            ->with([
                'assignedUser:id,name,employee_code',
                'originalUser:id,name,employee_code',
            ])
            ->when($originalUserId, fn($q) => $q->where('original_user_id', $originalUserId))
            ->when($assignedUserId, fn($q) => $q->where('assigned_user_id', $assignedUserId))
            ->when($status,         fn($q) => $q->where('status', $status))
            ->when($type,           fn($q) => $q->where('type', $type))

            ->when(
                $eventDateFrom && $eventDateTo,
                fn($q) => $q->whereBetween('event_date', [$eventDateFrom, $eventDateTo])
            )
            ->when(
                $eventDateFrom && !$eventDateTo,
                fn($q) => $q->whereDate('event_date', $eventDateFrom)
            )

            ->when(
                $leaveType !== null && $leaveType !== '',
                fn($q) => $q->whereLikeInsensitive('Leave_type', $leaveType)
            )
            ->when(
                $schoolName !== null && $schoolName !== '',
                fn($q) => $q->whereLikeInsensitive('school_name', $schoolName)
            )
            ->when(
                $title !== null && $title !== '',
                fn($q) => $q->whereLikeInsensitive('title', $title)
            )
            ->when(
                $lesson !== null && $lesson !== '',
                fn($q) => $q->whereLikeInsensitive('Lesson', $lesson)
            )

            ->orderBy('event_date')
            ->orderBy('school_name')
            ->orderBy('start_time')
            ->orderBy('assigned_user_id');
    }

    /**
     * イベントに設定する original_user_id / assigned_user_id の閲覧権限を検証
     * スコープ外のユーザーを不正に割り当てることを防ぐ
     */
    private function authorizeReferencedUsers(array $payload): void
    {
        foreach (['original_user_id', 'assigned_user_id'] as $key) {
            $userId = $payload[$key] ?? null;
            if ($userId === null || $userId === '') {
                continue;
            }

            $targetUser = User::findOrFail((int) $userId);
            $this->authorize('view', $targetUser);
        }
    }
}
