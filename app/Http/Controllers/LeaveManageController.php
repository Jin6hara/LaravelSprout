<?php

namespace App\Http\Controllers;

use App\Enums\LeaveExcused;
use App\Enums\LeaveKind;
use App\Enums\LeaveStatus;
use App\Http\Requests\LeaveManage\BulkUpdateLeaveManageRequest;
use App\Http\Requests\LeaveManage\StoreLeaveManageRequest;
use App\Http\Requests\LeaveManage\UpdateLeaveManageRequest;
use App\Models\Leave;
use App\Services\CurrentScopeService;
use Illuminate\Http\Request;
use Carbon\Carbon;


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
        $statusOptions = LeaveStatus::manageLabels();
        $kindOptions = LeaveKind::labels();
        $excusedOptions = LeaveExcused::managedLabels();

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

    /**
     * Leave を新規作成
     * POST /calendar/leaves — 指定日付に空の Leave レコードを生成（kind=special/status=approved で初期化）
     */
    public function store(StoreLeaveManageRequest $request)
    {
        $this->authorize('manage', Leave::class);

        $data = $request->validated();

        $leave = Leave::create([
            'user_id'       => null,
            'start_date'    => $data['start_date'],
            'end_date'      => null,
            'reason'        => null,
            'kind'          => LeaveKind::Special->value,
            'excused'       => LeaveExcused::Excused->value,
            'time_start'    => null,
            'time_end'      => null,
            'handle_type'   => null,
            'status'        => LeaveStatus::Approved->value,
            'district_id'   => $this->scopeService->currentDistrictId(),
            'department_id' => $this->scopeService->currentDepartmentId(),
        ]);

        return back()->with('toast', "Leave created at {$leave->start_date->format('Y-m-d')}.");
    }

    /**
     * Leave を1件更新
     * PATCH /calendar/leaves/{leave} — バリデーション済みデータで上書き保存。対象ユーザーへの manage 権限も確認
     */
    public function update(UpdateLeaveManageRequest $request, Leave $leave)
    {
        $this->authorize('update', $leave);

        $data = $request->validated();
        $targetUser = \App\Models\User::findOrFail((int) $data['user_id']);
        $this->authorize('manage', $targetUser);

        $leave->fill($data);
        $leave->save();

        return back()->with('toast', 'Leave updated.');
    }

    /**
     * Leave を一括更新（JSON API）
     * POST /calendar/leaves/bulk-update — items 配列を1件ずつ検証・保存し、更新件数を JSON で返す
     */
    public function bulkUpdate(BulkUpdateLeaveManageRequest $request)
    {
        $this->authorize('manage', Leave::class);

        $validated = $request->validated();

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

    /**
     * Leave を削除
     * DELETE /calendar/leaves/{leave} — 1件の Leave レコードを削除して一覧へ戻す
     */
    public function destroy(Request $request, Leave $leave)
    {
        $this->authorize('delete', $leave);

        $leave->delete();

        return back()->with('toast', 'Leave deleted.');
    }

}
