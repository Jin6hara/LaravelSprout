<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Validation\Rule;


class LeaveManageController extends Controller
{
    /**
     * Leaveの月次一覧（管理者用）
     * - 月/ユーザーで絞り込み
     * - イベントカード風スタイルで各行を編集UI化
     */
    public function edit(Request $request)
    {
        $viewer = Auth::user();
        if (!$viewer->hasRole(['admin', 'super_admin'])) {
            abort(403);
        }

        // クエリ: month（YYYY-MM）, user_id
        $month = (string)($request->input('month', now()->format('Y-m')));
        $userId = $request->integer('user_id') ?: null;

        // 月→期間
        try {
            [$y, $m] = explode('-', $month);
            $periodStart = Carbon::createFromDate((int)$y, (int)$m, 1)->startOfMonth();
        } catch (\Throwable $e) {
            $periodStart = now()->startOfMonth();
            $month = $periodStart->format('Y-m');
        }
        $periodEnd = (clone $periodStart)->endOfMonth();

        // ユーザー選択用
        $userOptions = User::query()
            ->select('id', 'first_name', 'family_name', 'employee_code')
            ->orderBy('employee_code')
            ->get();

        // 一覧取得（start_dateが月内にかかるもの中心、end_dateも考慮）
        $leaves = Leave::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->where(function ($q) use ($periodStart, $periodEnd) {
                // 期間重なり（start<=月末 && (end>=月初 || end nullでstartが月内)）
                $q->whereDate('start_date', '<=', $periodEnd->toDateString())
                    ->where(function ($qq) use ($periodStart) {
                        $qq->whereDate('end_date', '>=', $periodStart->toDateString())
                            ->orWhereNull('end_date');
                    });
            })
            ->with('user:id,first_name,family_name,employee_code')
            ->orderBy('start_date')
            ->orderBy('time_start')
            ->get();

        // セレクト用オプション
        $kindOptions = [
            'paid'            => 'ALP',
            'absense_to_paid' => 'MT → ALP',
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
        $statusOptions = [
            'approved' => 'Approved',
            'pending'  => 'Pending',
            'rejected' => 'Rejected',
            'other'    => 'Other',
        ];

        // Blade内で使う簡易フォーマッタ
        $fmtDate = fn($v) => $v ? Carbon::parse($v)->format('Y-m-d') : '';
        $fmtTime = fn($v) => $v ? Carbon::parse($v)->format('H:i') : '';

        return view('calendar.leaveEdit', [
            'month'          => $month,
            'periodStart'    => $periodStart,
            'periodEnd'      => $periodEnd,
            'leaves'         => $leaves,
            'userOptions'    => $userOptions,
            'kindOptions'    => $kindOptions,
            'excusedOptions' => $excusedOptions,
            'statusOptions'  => $statusOptions,
            'fmtDate'        => $fmtDate,
            'fmtTime'        => $fmtTime,
        ]);
    }

    /** 保存（更新） */
    public function update(Request $request, Leave $leave)
    {
        // 権限（念のため。ルートにもmiddlewareあり）
        if (!Auth::user()->hasRole(['admin', 'super_admin'])) {
            abort(403);
        }

        $data = $this->validateLeave($request);

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

        return back()->with('status', 'Leave updated.');
    }

    /** 削除 */
    public function destroy(Request $request, Leave $leave)
    {
        if (!Auth::user()->hasRole(['admin', 'super_admin'])) {
            abort(403);
        }

        $leave->delete();

        return back()->with('status', 'Leave deleted.');
    }

    /** 共通バリデーション */
    private function validateLeave(Request $request): array
    {
        $kindValues   = ['paid', 'absense_to_paid', 'special', 'absence', 'adjustment', 'left_early', 'late', 'other'];
        $excusedValues = ['excused', 'unexcused'];
        $statusValues = ['approved', 'pending', 'rejected', 'other'];

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

    /** 空白Leave 追加 */
    public function storeBlank(Request $request)
    {
        if (!auth()->user()->hasRole(['admin', 'super_admin'])) {
            abort(403);
        }

        // 必須: user_id, start_date
        $data = $request->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
        ]);

        // デフォルト値（必要ならここを調整）
        $leave = Leave::create([
            'user_id'     => $data['user_id'],
            'start_date'  => $data['start_date'],
            'end_date'    => null,
            'reason'      => null,
            'kind'        => 'other',        // 既定で other
            'excused'     => 'unexcused',    // 既定で非公認
            'special_type' => null,
            'time_start'  => null,
            'time_end'    => null,
            'handle_type' => null,
            'status'      => 'pending',      // 既定で pending（承認フローに載せるなら）
        ]);

        return back()->with('status', "空白Leaveを {$leave->start_date->format('Y-m-d')} に追加しました。");
    }

    /** 一括更新 */
    public function bulkUpdate(Request $request)
    {
        if (!auth()->user()->hasRole(['admin', 'super_admin'])) {
            abort(403);
        }

        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return response()->json(['ok' => false, 'message' => '対象がありません'], 422);
        }

        // 許容値
        $kindValues    = ['paid', 'absense_to_paid', 'special', 'absence', 'adjustment', 'left_early', 'late', 'other'];
        $excusedValues = ['excused', 'unexcused'];
        $statusValues  = ['approved', 'pending', 'rejected', 'other'];

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
            $leave = Leave::find($it['id']);
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
        session()->flash('status', "一括保存しました（{$updated} 件）");

        return response()->json([
            'ok'      => true,
            'updated' => $updated,
            'failed'  => 0,
            'message' => '一括保存しました。',
        ]);
    }
}
