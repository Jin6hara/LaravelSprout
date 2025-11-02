<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\Leave;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    public function create()
    {
        $user = auth()->user();
        return view('leaves.create', [
            'defaultUserId' => $user->id,
        ]);
    }

    public function store(StoreLeaveRequest $request)
    {
        // 承認フローがある場合は pending から始めるなど調整してください
        $leave = Leave::create([
            'user_id'     => (int)$request->input('user_id'),
            'start_date'  => $request->date('start_date'),
            'end_date'    => $request->input('end_date') ? $request->date('end_date') : null,
            'kind'        => $request->input('kind'),
            'excused'     => $request->input('excused', 'unexcused'),
            'special_type' => $request->input('special_type'),
            'reason'      => $request->input('reason'),
            'time_start'  => $request->input('time_start'),
            'time_end'    => $request->input('time_end'),
            'status'      => $request->input('status', 'approved'),
            'approved_by' => auth()->id(), // 簡易に自分で承認した体
        ]);

        // 作成日のカレンダー画面に遷移
        $date = optional($leave->start_date)->format('Y-m-d');

        // Observer が自動でスナップショット生成
        return redirect()->to(route('calendar.edit') . '?event_date=' . urlencode($date))
            ->with('status', '欠席登録成功。通常シフトある場合はEventが作成されます。');
    }


    public function cancel(Leave $leave)
    {
        $this->authorize('cancel', $leave);

        DB::transaction(function () use ($leave) {
            $wasApproved = $leave->status === 'approved';

            $leave->update(['status' => 'cancelled']);

            if ($wasApproved) {
                app(LeaveBalanceService::class)->revert($leave);
            }
        });

        return back()->with('success', '申請を取り消しました。');
    }

    /**
     * 欠席報告画面
     * - 一般ユーザー：自分のみ
     * - 管理者(admin|super_admin)：誰のページでも閲覧可能（そのユーザーのデータのみ表示）
     */
    public function absence(Request $request, User $user)
    {
        $viewer  = Auth::user();
        $isAdmin = $viewer->hasRole(['admin', 'super_admin']);
        if (!$isAdmin && $viewer->id !== $user->id) {
            abort(403);
        }

        // 表示するレコード
        $leaves = Leave::query()
            ->where('user_id', $user->id)
            ->whereIn('kind', ['absence', 'absence_to_paid', 'other'])
            ->orderByDesc('start_date')
            ->get();

        // ラベル
        $kindLabels = [
            'absence'         => 'Unpaid Leave',
            'absence_to_paid' => 'ALP',
            'other'           => 'Others',
        ];

        // Handle Type 選択肢（キー＝保存値、値＝表示文言）
        $handleTypeOptions = [
            'apply_alp'       => 'I will apply for an ALP via HR Brain for this date of absence.',
            'no_alp'          => 'I will not use an ALP for this absence (non-paid absence).',
            'clinic'          => 'I will submit a clinic receipt for this non-paid absence.',
            'sick_child'      => 'I will use Sick or Injured Child Care Leave for this absence.',
            'special_leave'   => 'I will use Special Leave for this absence.',
            'menstrual_leave' => 'I will use Menstrual Leave for this absence.',
        ];

        // ★ ビューに渡す「行用のビューモデル」を生成
        $rows = $leaves->map(function (Leave $leave) use ($kindLabels, $handleTypeOptions) {
            $kindLabel = $leave->kind === 'other'
                ? ($leave->special_type ?: 'Others')
                : ($kindLabels[$leave->kind] ?? ucfirst($leave->kind));

            // reason の null 判定は外す：kind=absence かつ handle_type が null のとき入力可
            $needsReport = ($leave->kind === 'absence') && is_null($leave->handle_type);

            $statusText = ($leave->handle_type)
                ? 'Submitted'
                : ($needsReport ? 'Required' : 'Submitted');

            $dateMain = optional($leave->start_date)->toDateString();
            $dateSub  = (!empty($leave->end_date) && $leave->end_date && $leave->end_date->ne($leave->start_date))
                ? $leave->end_date->toDateString()
                : null;

            $formId = 'report-form-' . $leave->id;

            $handleLabel = $leave->handle_type
                ? ($handleTypeOptions[$leave->handle_type] ?? $leave->handle_type)
                : '';

            return [
                'leave'        => $leave,             // そのまま使う（route引数用）
                'kindLabel'    => $kindLabel,
                'needsReport'  => $needsReport,
                'statusText'   => $statusText,
                'dateMain'     => $dateMain,
                'dateSub'      => $dateSub,
                'formId'       => $formId,
                'handleLabel'  => $handleLabel,
            ];
        });

        return view('calendar.absenceReport', [
            'user'               => $user,
            'rows'               => $rows,
            'kindLabels'         => $kindLabels,
            'handleTypeOptions'  => $handleTypeOptions,
        ]);
    }


    /**
     * 欠席の自己報告（Reason + Handle Type）
     * - 対象：kind=absence かつ reason/handle_type が両方 null のもの
     * - 送信後は編集不可（＝両方値が入ったらSubmittedとみなす）
     */
    public function report(Request $request, Leave $leave)
    {
        $viewer  = Auth::user();
        $isAdmin = $viewer->hasRole(['admin', 'super_admin']);
        if (!$isAdmin && $viewer->id !== $leave->user_id) {
            abort(403);
        }

        if ($leave->kind !== 'absence') {
            return back()->with('error', 'This leave is not target for absence self-report.');
        }
        if (!is_null($leave->handle_type)) {
            return back()->with('error', 'Already submitted.');
        }

        $validated = $request->validate([
            'reason'      => ['required', 'string', 'max:1000'],
            'handle_type' => ['required', 'in:apply_alp,no_alp,clinic,sick_child,special_leave,menstrual_leave'],
        ]);

        $leave->update([
            'reason'      => $validated['reason'],
            'handle_type' => $validated['handle_type'],
        ]);

        return back()->with('status', 'Submitted.');
    }
}
