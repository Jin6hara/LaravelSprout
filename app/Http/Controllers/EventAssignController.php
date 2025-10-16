<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class EventAssignController extends Controller
{
    public function edit(Request $request)
    {
        $userOptions = \App\Models\User::query()
            ->select('id', 'name', 'employee_code')
            ->orderBy('employee_code')
            ->limit(500)
            ->get();

        // 検索パラメータ取得
        $originalUserId = $request->input('original_user_id');
        $assignedUserId = $request->input('assigned_user_id');
        $status         = $request->input('status');
        $type           = $request->input('type');
        $eventDate      = $request->input('event_date');
        $leaveType      = $request->input('Leave_type');
        $schoolName     = $request->input('school_name');
        $title          = $request->input('title');
        $lesson         = $request->input('Lesson');

        $events = \App\Models\Event::query()
            ->with(['assignedUser:id,name,employee_code', 'originalUser:id,name,employee_code'])
            ->when($originalUserId, fn($q) => $q->where('original_user_id', $originalUserId))
            ->when($assignedUserId, fn($q) => $q->where('assigned_user_id', $assignedUserId))
            ->when($status,         fn($q) => $q->where('status', $status))
            ->when($type,           fn($q) => $q->where('type', $type))
            ->when($eventDate,      fn($q) => $q->whereDate('event_date', $eventDate))
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
            'pending' => 'Pending',
            'in_process' => 'In Process',
            'fixed' => 'Fixed',
            'filled' => 'Filled',
        ];
        $typeOptions = [
            'regular_time'         => 'RT',
            'overtime'             => 'OT',
            'schedule_change'      => 'SC',
            'special'              => 'SP',
            'rostered_working_day' => 'RWD',
            'none_required'        => 'NS',
        ];

        return view('calendar.edit', compact('events', 'userOptions', 'statusOptions', 'typeOptions'));
    }

    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'event_date'        => ['required', 'date'],
            'original_user_id'  => ['nullable', 'exists:users,id'],
            'leave_kind'        => ['nullable', Rule::in(['paid', 'absense_to_paid', 'special', 'absence', 'adjustment', 'other'])],
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

        // ⬇︎ Leave Type 同期：フォームの leave_kind を Event.Leave_type に反映（指定があるときだけ）
        if ($request->filled('leave_kind')) {
            $event->Leave_type = $request->input('leave_kind'); // ※ Event 側カラムは大文字 L
        }

        // ⬇︎ その他の項目を一括反映
        $event->fill($validated)->save();

        // ⬇︎ 関連 Leave がある場合は kind も同期（events.source_leave_id 経由）
        if (!empty($event->source_leave_id) && !empty($event->Leave_type)) {
            // leave_kind が absence 以外 → excused、absence → unexcused
            $excused = ($event->Leave_type === 'absence') ? 'unexcused' : 'excused';

            Leave::where('id', $event->source_leave_id)
                ->update([
                    'kind'    => $event->Leave_type,
                    'excused' => $excused,            // excused を同時更新
                ]);
        }

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
            'leave_kind'        => ['nullable', Rule::in(['paid', 'absense_to_paid', 'special', 'absence', 'adjustment', 'other'])],
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

        // ⬇︎ フォームの leave_kind を Event.Leave_type に反映（指定があるときだけ）
        if (array_key_exists('leave_kind', $validated)) {
            $validated['Leave_type'] = $validated['leave_kind']; // ※ Event 側カラムは大文字 L
            unset($validated['leave_kind']);
        }

        // ⬇︎ H:i → H:i:s に正規化（nullはそのまま）
        foreach (['start_time', 'end_time'] as $k) {
            if ($request->filled($k)) {
                $validated[$k] = \Illuminate\Support\Carbon::createFromFormat('H:i', $request->input($k))->format('H:i:s');
            } else {
                $validated[$k] = null;
            }
        }

        // ⬇︎ total_duration 未入力なら自動計算（Observer があれば最終整合はそちらでも取れます）
        if (!$request->filled('total_duration') && $validated['start_time'] && $validated['end_time']) {
            $start = \Illuminate\Support\Carbon::createFromFormat('H:i:s', $validated['start_time']);
            $end   = \Illuminate\Support\Carbon::createFromFormat('H:i:s', $validated['end_time']);
            if ($end->lt($start)) $end->addDay(); // 日跨ぎ
            $mins = $start->diffInMinutes($end);
            $validated['total_duration'] = sprintf('%d:%02d', intdiv($mins, 60), $mins % 60);
        }

        Event::create($validated);

        return back()->with('status', 'イベントを複写しました。');
    }

    public function storeBlank()
    {
        // 空白イベントを作成（最低限: 今日の日付・status=pending）
        $event = Event::create([
            'event_date' => now()->toDateString(),
            'status'     => 'pending',
            'type'       => 'regular_time',
        ]);

        return back()->with('status', '空白イベントを追加しました。');
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
            'leave_kind'        => ['nullable', Rule::in(['paid', 'absense_to_paid', 'special', 'absence', 'adjustment', 'other'])],
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

            // ⬇︎ Leave Type 同期：leave_kind → Event.Leave_type へ（指定があるときだけ）
            if (array_key_exists('leave_kind', $data)) {
                $event->Leave_type = $data['leave_kind']; // ※ Event 側カラムは大文字 L
                unset($data['leave_kind']);
            }

            // ⬇︎ 他の項目を一括反映
            $event->fill($data)->save(); // Observer があれば最終整合を取ってくれます

            // ⬇︎ 関連 Leave がある場合は kind も同期（events.source_leave_id 経由）
            if (!empty($event->source_leave_id) && !empty($event->Leave_type)) {
                $excused = ($event->Leave_type === 'absence') ? 'unexcused' : 'excused';

                Leave::where('id', $event->source_leave_id)
                    ->update([
                        'kind'    => $event->Leave_type,
                        'excused' => $excused,            // excused を同時更新
                    ]);
            }

            $results[] = ['id' => $event->id, 'ok' => true];
        }

        $okCount = collect($results)->where('ok', true)->count();
        $ngCount = count($results) - $okCount;

        return response()->json([
            'ok'      => $ngCount === 0,
            'updated' => $okCount,
            'failed'  => $ngCount,
            'results' => $results,
            'message' => $ngCount ? '一部保存に失敗しました' : 'すべて保存しました',
        ]);
    }
}
