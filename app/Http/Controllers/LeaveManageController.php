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
    // 1) ユーザー候補
    $userOptions = User::query()
        ->select('id', 'first_name', 'family_name', 'employee_code')
        ->orderBy('employee_code')
        ->limit(500)
        ->get();

    // 2) パラメータ取得
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
        ->when($userId,      fn($q) => $q->where('user_id', $userId))
        ->when($kind,        fn($q) => $q->where('kind', $kind))
        ->when($excused,     fn($q) => $q->where('excused', $excused))
        ->when($status,      fn($q) => $q->where('status', $status))
        ->when($specialType, fn($q) => $q->where('special_type', 'like', '%'.$specialType.'%'))
        ->when($handleType,  fn($q) => $q->where('handle_type',  'like', '%'.$handleType.'%'))
        ->when($reason,      fn($q) => $q->where('reason',      'like', '%'.$reason.'%'))
        // ⬇︎ 日付フィルタ（start & end ⇒ 期間, startのみ ⇒ 当日）
        ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
            $q->whereDate('start_date', '<=', $endDate)
              ->where(function($qq) use ($startDate) {
                  $qq->whereDate('end_date', '>=', $startDate)
                     ->orWhereNull('end_date');
              });
        })
        ->when($startDate && !$endDate, function ($q) use ($startDate) {
            $q->whereDate('start_date', '<=', $startDate)
              ->where(function($qq) use ($startDate) {
                  $qq->whereDate('end_date', '>=', $startDate)
                     ->orWhereNull('end_date');
              });
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
        'other'    => 'Other',
    ];
    $kindOptions = [
        'paid'            => 'Paid',
        'absense_to_paid' => 'Absence→Paid',
        'special'         => 'Special',
        'absence'         => 'Absence',
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
            'user_id'      => null,
            'start_date'   => $data['start_date'],
            'end_date'     => null,
            'reason'       => null,
            'kind'         => 'special',
            'excused'      => 'excused',
            'time_start'   => null,
            'time_end'     => null,
            'handle_type'  => null,
            'status'       => 'pending',
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
