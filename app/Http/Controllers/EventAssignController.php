<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class EventAssignController extends Controller
{
    public function edit(Request $request)
    {
        $userOptions = User::query()
            ->select('id', 'first_name', 'family_name', 'employee_code')
            ->orderBy('employee_code')
            ->limit(500)
            ->get();

        // ★追加：School名の候補（重複除去＆名前順）
        $schoolNames = School::query()
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
                $q->where('Leave_type', 'like', '%' . $leaveType . '%')
            )
            ->when(
                $schoolName !== null && $schoolName !== '',
                fn($q) =>
                $q->where('school_name', 'like', '%' . $schoolName . '%')
            )
            ->when(
                $title !== null && $title !== '',
                fn($q) =>
                $q->where('title', 'like', '%' . $title . '%')
            )
            ->when(
                $lesson !== null && $lesson !== '',
                fn($q) =>
                $q->where('Lesson', 'like', '%' . $lesson . '%')
            )
            ->orderByDesc('event_date')
            ->orderBy('school_name')
            ->orderBy('start_time') // 効かない：15時が10時より前に来る。FullCalendarも同じ問題。
            ->orderBy('assigned_user_id')
            ->paginate(24)
            ->withQueryString();

        $statusOptions = [
            'filled'     => 'Filled',
            'fixed'      => 'Fixed',
            'pending'    => 'Pending',
            'in_process' => 'In Process',
        ];
        $typeOptions = [
            'regular_time'         => 'RT',
            'overtime'             => 'OT',
            'schedule_change'      => 'SC',
            'special'              => 'SP',
            'rostered_working_day' => 'RWD',
            'none_required'        => 'NS',
        ];

        // $schoolNames を view に渡す
        return view('calendar.edit', compact('events', 'userOptions', 'statusOptions', 'typeOptions', 'schoolNames'));
    }

    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'event_date'        => ['required', 'date'],
            'original_user_id'  => ['nullable', 'exists:users,id'],
            'Leave_type'        => ['nullable', 'string'],
            'title'             => ['nullable', 'string', 'max:255'],
            'school_name'       => ['nullable', 'string', 'max:255'],

            // ⬇︎ ここを date_format から regex に変える（HH:mm または HH:mm:ss）
            'start_time'        => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time'          => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],

            'total_duration'    => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'], // H:MM
            'Lesson'            => ['nullable', 'string'],
            'assigned_user_id'  => ['nullable', 'exists:users,id'],
            'status'            => ['required', Rule::in(['pending', 'fixed', 'filled', 'in_process'])],
            'type'              => ['required', Rule::in(['regular_time', 'none_required', 'overtime', 'schedule_change', 'rostered_working_day', 'special'])],
            'notes'             => ['nullable', 'string'],
        ]);

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

        return back()->with('status', 'イベントを更新しました。');
    }

    public function destroy(Request $request, Event $event)
    {
        // 必要ならポリシー/権限チェックをここで
        $event->delete();
        return back()->with('status', 'イベントを削除しました。');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_date'        => ['required', 'date'],
            'original_user_id'  => ['nullable', 'exists:users,id'],
            'Leave_type'        => ['nullable', 'string'],
            'title'             => ['nullable', 'string', 'max:255'],
            'school_name'       => ['nullable', 'string', 'max:255'],
            // 既存：H:i 固定（update と違い、ここは仕様そのまま）
            'start_time'        => ['nullable', 'date_format:H:i'],
            'end_time'          => ['nullable', 'date_format:H:i'],
            'total_duration'    => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'], // H:MM
            'Lesson'            => ['nullable', 'string'],
            'assigned_user_id'  => ['nullable', 'exists:users,id'],
            'status'            => ['required', Rule::in(['pending', 'fixed', 'filled', 'in_process'])],
            'type'              => ['required', Rule::in(['regular_time', 'none_required', 'overtime', 'schedule_change', 'rostered_working_day', 'special'])],
            'notes'             => ['nullable', 'string'],
        ]);


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

        Event::create($validated);

        return back()->with('status', 'イベントを複写しました。');
    }

    public function storeBlank(Request $request)
    {
        // リクエストやURLクエリから event_date を取得（POSTでもGETでも対応）
        $date = $request->input('event_date', now()->toDateString());

        // 空白イベントを作成
        $event = Event::create([
            'event_date' => $date,
            'status'     => 'pending',
            'type'       => 'regular_time',
        ]);

        return back()->with('status', "空白イベントを {$date} に追加しました。");
    }

    public function bulkUpdate(Request $request)
    {
        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return response()->json(['ok' => false, 'message' => '対象がありません'], 422);
        }

        $rules = [
            'id'                => ['required', 'integer', 'exists:events,id'],
            'event_date'        => ['required', 'date'],
            'original_user_id'  => ['nullable', 'exists:users,id'],
            'Leave_type'        => ['nullable', 'string'],
            'title'             => ['nullable', 'string', 'max:255'],
            'school_name'       => ['nullable', 'string', 'max:255'],
            'start_time'        => ['nullable', 'date_format:H:i'],
            'end_time'          => ['nullable', 'date_format:H:i'],
            'total_duration'    => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'], // H:MM
            'Lesson'            => ['nullable', 'string'],
            'assigned_user_id'  => ['nullable', 'exists:users,id'],
            'status'            => ['required', Rule::in(['pending', 'fixed', 'filled', 'in_process'])],
            'type'              => ['required', Rule::in(['regular_time', 'none_required', 'overtime', 'schedule_change', 'rostered_working_day', 'special'])],
            'notes'             => ['nullable', 'string'],
        ];

        $results = [];
        foreach ($items as $row) {
            $v = Validator::make($row, $rules);
            if ($v->fails()) {
                $results[] = ['id' => $row['id'] ?? null, 'ok' => false, 'errors' => $v->errors()->toArray()];
                continue;
            }
            $data = $v->validated();

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

            $event = Event::find($data['id']);
            $event->fill($data)->save(); // Observerがあれば最終整合を取ってくれます

            $results[] = ['id' => $event->id, 'ok' => true];
        }

        $okCount = collect($results)->where('ok', true)->count();
        $ngCount = count($results) - $okCount;

        // ✅ JSON返却でもフラッシュを仕込む
        $flash = $ngCount ? "一部保存に失敗しました（保存: {$okCount} / 失敗: {$ngCount}）" : "すべて保存しました（{$okCount} 件）";
        session()->flash('status', $flash);

        return response()->json([
            'ok'      => $ngCount === 0,
            'updated' => $okCount,
            'failed'  => $ngCount,
            'results' => $results,
            'message' => $ngCount ? '一部保存に失敗しました' : 'すべて保存しました',
        ]);
    }
}
