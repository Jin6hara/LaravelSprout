<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Services\CurrentScopeService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Validation\Rule;


class LeaveManageController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    /**
     * Leaveの月次一覧（管理者用）
     * - 月/ユーザーで絞り込み
     * - イベントカード風スタイルで各行を編集UI化
     */
    public function edit(Request $request)
    {
        $this->authorize('manage', Leave::class);

        // 1) ユーザー候補
        $userOptions = $this->scopeService->targetUserQuery()
            ->select('id', 'first_name', 'family_name', 'employee_code')
            ->orderBy('employee_code')
            ->limit(500)
            ->get();

        // 2) パラメータ取得
        $leaveId     = $request->input('leave_id'); // ★個別編集用パラメータ
        $userId      = $request->input('user_id');
        $kind        = $request->input('kind');
        $excused     = $request->input('excused');
        $status      = $request->input('status');
        $specialType = $request->input('special_type');
        $handleType  = $request->input('handle_type');
        $reason      = $request->input('reason');

        $startDate   = $request->input('start_date');
        $endDate     = $request->input('end_date');

        // 3) 日付ルール
        // end のみ → エラー
        if ($endDate && !$startDate) {
            return back()
                ->withErrors(['start_date' => '開始日を指定してください。'])
                ->withInput();
        }

        // start > end → エラー
        if ($startDate && $endDate && $startDate > $endDate) {
            return back()
                ->withErrors(['start_date' => '開始日は終了日より前に設定してください。'])
                ->withInput();
        }

        // 日付フォーマット関数
        $fmtDate = fn($v) => $v ? Carbon::parse($v)->format('Y-m-d') : '';
        $fmtTime = fn($v) => $v ? Carbon::parse($v)->format('H:i')   : '';

        // 4) クエリ（Event版と同順序・構成）
        $leaves = Leave::query()
            ->with(['user:id,first_name,family_name,employee_code'])
            ->where('district_id', $this->scopeService->currentDistrictId())
            ->where('department_id', $this->scopeService->currentDepartmentId())
            ->when($leaveId,     fn($q) => $q->where('id', $leaveId)) // ★個別編集用パラメータ: leave_id があれば最優先で一意絞り込み
            ->when($userId,      fn($q) => $q->where('user_id', $userId))
            ->when($kind,        fn($q) => $q->where('kind', $kind))
            ->when($excused,     fn($q) => $q->where('excused', $excused))
            ->when($status,      fn($q) => $q->where('status', $status))
            ->when($specialType, fn($q) => $q->whereLikeInsensitive('special_type', $specialType))
            ->when($handleType,  fn($q) => $q->whereLikeInsensitive('handle_type', $handleType))
            ->when($reason,      fn($q) => $q->whereLikeInsensitive('reason', $reason))
            // ⬇︎ 日付フィルタ（単日= start_date、期間= start_date..end_date）
            //   → end_effective = COALESCE(end_date, start_date) として区間重なりを一本化
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereDate('start_date', '<=', $endDate)
                  ->whereRaw('DATE(COALESCE(end_date, start_date)) >= ?', [$startDate]);
            })
            ->when($startDate && !$endDate, function ($q) use ($startDate) {
                $q->whereDate('start_date', '<=', $startDate)
                  ->whereRaw('DATE(COALESCE(end_date, start_date)) >= ?', [$startDate]);
            })
            ->orderByDesc('start_date')
            ->orderBy('time_start')
            ->orderBy('user_id')
            ->paginate(24)
            ->withQueryString();

        // 5) オプション
        $statusOptions = [
            'approved' => 'Approved',
            'pending'  => 'Pending',
            'rejected' => 'Rejected',
            'cancelled'    => 'Cancelled',
        ];
        $kindOptions = [
            'paid'            => 'ALP',
            'absence_to_paid' => 'MT to ALP',
            'special'         => 'Special',
            'absence'         => 'MT',
            'adjustment'      => 'Adjustment',
            'left_early'      => 'Left Early',
            'late'            => 'Late',
            'other'           => 'Other',
        ];
        $excusedOptions = [
            'excused'   => 'Excused',
            'unexcused' => 'Unexcused',
        ];

        // 6) 表示
        return view('calendar.leaveEdit', compact(
            'leaves',
            'userOptions',
            'statusOptions',
            'kindOptions',
            'excusedOptions',
            'fmtDate',
            'fmtTime'
        ));
    }

    /** 新規作成 */
    public function store(Request $request)
    {
        $this->authorize('manage', Leave::class);

        $data = $request->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
        ]);

        $leave = Leave::create([
            'user_id'       => null,
            'start_date'    => $data['start_date'],
            'end_date'      => null,
            'reason'        => null,
            'kind'          => 'special',
            'excused'       => 'excused',
            'time_start'    => null,
            'time_end'      => null,
            'handle_type'   => null,
            'status'        => 'approved',
            'district_id'   => $this->scopeService->currentDistrictId(),
            'department_id' => $this->scopeService->currentDepartmentId(),
        ]);

        return back()->with('toast', "Leave created at {$leave->start_date->format('Y-m-d')}.");
    }

    /** 保存（更新） */
    public function update(Request $request, Leave $leave)
    {
        $this->authorize('update', $leave);

        $data = $this->validateLeave($request);
        $targetUser = \App\Models\User::findOrFail((int) $data['user_id']);
        $this->authorize('manage', $targetUser);

        // ビジネスルール（時間の一貫性：片方のみ指定 → バリデーションで弾く）
        // 追加チェック（任意）：time_start < time_end
        if (!empty($data['time_start']) && !empty($data['time_end'])) {
            $ts = Carbon::createFromFormat('H:i', $data['time_start']);
            $te = Carbon::createFromFormat('H:i', $data['time_end']);
            if ($te->lessThanOrEqualTo($ts)) {
                return $this->respondValidationError($request, [
                    'time_end' => ['End time must be after Start time.'],
                ]);
            }
        }

        $leave->fill($data);
        $leave->save();

        return back()->with('toast', 'Leave updated.');
    }

    /** 一括更新 */
    public function bulkUpdate(Request $request)
    {
        $this->authorize('manage', Leave::class);

        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return response()->json(['ok' => false, 'message' => '対象がありません'], 422);
        }

        // 許容値
        $kindValues    = ['paid', 'absence_to_paid', 'special', 'absence', 'adjustment', 'left_early', 'late', 'other'];
        $excusedValues = ['excused', 'unexcused'];
        $statusValues  = ['approved', 'pending', 'rejected', 'draft', 'cancelled', 'archived'];

        // items.* で配列バリデーション
        $validated = $request->validate([
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.id'                     => ['required', 'integer', 'exists:leaves,id'],
            'items.*.user_id'                => ['required', 'integer', 'exists:users,id'],
            'items.*.start_date'             => ['required', 'date'],
            'items.*.end_date'               => ['nullable', 'date', 'after_or_equal:items.*.start_date'],
            'items.*.reason'                 => ['nullable', 'string'],
            'items.*.kind'                   => ['required', Rule::in($kindValues)],
            'items.*.excused'                => ['required', Rule::in($excusedValues)],
            'items.*.special_type'           => ['nullable', 'string', 'max:100'],
            'items.*.time_start'             => ['nullable', 'date_format:H:i', 'required_with:items.*.time_end'],
            'items.*.time_end'               => ['nullable', 'date_format:H:i', 'required_with:items.*.time_start'],
            'items.*.handle_type'            => ['nullable', 'string'],
            'items.*.status'                 => ['required', Rule::in($statusValues)],
        ]);

        // 追加の論理チェック（start < end）
        foreach ($validated['items'] as $it) {
            if (!empty($it['time_start']) && !empty($it['time_end'])) {
                $ts = Carbon::createFromFormat('H:i', $it['time_start']);
                $te = Carbon::createFromFormat('H:i', $it['time_end']);
                if ($te->lessThanOrEqualTo($ts)) {
                    return response()->json([
                        'ok' => false,
                        'message' => "ID {$it['id']}: End time must be after Start time."
                    ], 422);
                }
            }
        }

        // 一括反映
        foreach ($validated['items'] as $it) {
            $leave = Leave::findOrFail($it['id']);
            $this->authorize('update', $leave);

            $targetUser = \App\Models\User::findOrFail((int) $it['user_id']);
            $this->authorize('manage', $targetUser);

            $leave->fill([
                'user_id'     => $it['user_id'],
                'start_date'  => $it['start_date'],
                'end_date'    => $it['end_date'] ?? null,
                'reason'      => $it['reason'] ?? null,
                'kind'        => $it['kind'],
                'excused'     => $it['excused'],
                'special_type' => $it['special_type'] ?? null,
                'time_start'  => $it['time_start'] ?? null,
                'time_end'    => $it['time_end'] ?? null,
                'handle_type' => $it['handle_type'] ?? null,
                'status'      => $it['status'],
            ]);
            $leave->save();
        }

        $updated = count($validated['items']);

        // ✅ JSON返却でもフラッシュを仕込む
        session()->flash('toast', "Updated {$updated} leave(s)");

        return response()->json([
            'ok'      => true,
            'updated' => $updated,
            'failed'  => 0,
            'message' => '一括保存しました。',
        ]);
    }

    /** 削除 */
    public function destroy(Request $request, Leave $leave)
    {
        $this->authorize('delete', $leave);

        $leave->delete();

        return back()->with('toast', 'Leave deleted.');
    }

    /** 共通バリデーション */
    private function validateLeave(Request $request): array
    {
        $kindValues   = ['paid', 'absence_to_paid', 'special', 'absence', 'adjustment', 'left_early', 'late', 'other'];
        $excusedValues = ['excused', 'unexcused'];
        $statusValues = ['approved', 'pending', 'rejected', 'draft', 'cancelled', 'archived'];

        return $request->validate([
            'user_id'      => ['required', 'integer', 'exists:users,id'],
            'start_date'   => ['required', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason'       => ['nullable', 'string'],
            'kind'         => ['required', Rule::in($kindValues)],
            'excused'      => ['required', Rule::in($excusedValues)],
            'special_type' => ['nullable', 'string', 'max:100'],
            'time_start'   => ['nullable', 'date_format:H:i', 'required_with:time_end'],
            'time_end'     => ['nullable', 'date_format:H:i', 'required_with:time_start'],
            'handle_type'  => ['nullable', 'string'],
            'status'       => ['required', Rule::in($statusValues)],
        ]);
    }

    /** JSON/HTML 両対応のバリデーションエラー返却 */
    private function respondValidationError(Request $request, array $errors)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation error.',
                'errors' => $errors,
            ], 422);
        }
        return back()->withErrors($errors)->withInput();
    }

}
