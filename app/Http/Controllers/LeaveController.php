<?php

namespace App\Http\Controllers;

use App\Enums\LeaveExcused;
use App\Enums\LeaveKind;
use App\Enums\LeaveStatus;
use App\Http\Requests\Leave\AllReportRequest;
use App\Http\Requests\Leave\ReportLeaveRequest;
use App\Http\Requests\Leave\StoreLeaveRequest;
use App\Models\Leave;
use App\Models\User;
use App\Services\CurrentScopeService;
use App\Services\LeaveBalanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeaveController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    /**
     * 休暇登録フォーム表示（管理者用）
     * GET /leaves/create
     */
    public function create()
    {
        $user = auth()->user();
        return view('leaves.create', [
            'defaultUserId' => $user->id,
        ]);
    }

    /**
     * 休暇を直接登録（承認フローなし・管理者用）
     * POST /leaves — 登録後は対象日のカレンダー画面へリダイレクト
     */
    public function store(StoreLeaveRequest $request)
    {
        // 承認フローがある場合は pending から始めるなど調整してください
        $targetUser = \App\Models\User::find((int) $request->input('user_id'));
        abort_if(! $targetUser, 404);
        $this->authorize('manage', $targetUser);

        $leave = Leave::create([
            'user_id'       => (int)$request->input('user_id'),
            'start_date'    => $request->date('start_date'),
            'end_date'      => $request->input('end_date') ? $request->date('end_date') : null,
            'kind'          => $request->input('kind'),
            'excused'       => $request->input('excused', LeaveExcused::Unexcused->value),
            'special_type'  => $request->input('special_type'),
            'reason'        => $request->input('reason'),
            'time_start'    => $request->input('time_start'),
            'time_end'      => $request->input('time_end'),
            'status'        => $request->input('status', LeaveStatus::Approved->value),
            'approved_by'   => auth()->id(), // 簡易に自分で承認した体
            'district_id'   => $targetUser?->district_id,
            'department_id' => $targetUser?->department_id,
        ]);

        // 作成日のカレンダー画面に遷移
        $date = optional($leave->start_date)->format('Y-m-d');

        // Observer が自動でスナップショット生成
        return redirect()->to(route('calendar.edit') . '?event_date=' . urlencode($date))
            ->with('toast', 'Absence successfully registered. If a regular shift exists for that day, an Event will be created automatically.');
    }


    /**
     * 休暇申請の取消
     * 承認済みの場合は LeaveBalanceService で有給残を返戻する
     */
    public function cancel(Leave $leave)
    {
        $this->authorize('cancel', $leave);

        DB::transaction(function () use ($leave) {
            $wasApproved = $leave->status === LeaveStatus::Approved->value;

            $leave->update(['status' => LeaveStatus::Cancelled->value]);

            if ($wasApproved) {
                app(LeaveBalanceService::class)->revert($leave);
            }
        });

        return back()->with('toast', '申請を取り消しました。');
    }

    /**
     * 欠席報告画面
     * - 一般ユーザー：自分のみ
     * - 管理者(admin|super_admin)：誰のページでも閲覧可能（そのユーザーのデータのみ表示）
     */
    public function absence(User $user)
    {
        $this->authorize('view', $user);

        // 表示するレコード
        $leaves = Leave::query()
            ->where('user_id', $user->id)
            ->whereIn('kind', LeaveKind::absenceReportValues())
            ->orderByDesc('start_date')
            ->get();

        // ラベル
        $kindLabels = LeaveKind::absenceReportLabels();

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
            $kindLabel = $leave->kind === LeaveKind::Other->value
                ? ($leave->special_type ?: 'Others')
                : ($kindLabels[$leave->kind] ?? ucfirst($leave->kind));

            // reason の null 判定は外す：kind=absence かつ handle_type が null のとき入力可
            $needsReport = ($leave->kind === LeaveKind::Absence->value) && is_null($leave->handle_type);

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
    public function report(ReportLeaveRequest $request, Leave $leave)
    {
        $this->authorize('report', $leave);

            if ($leave->kind !== LeaveKind::Absence->value) {
            return back()->with('toast_errors', ['This leave is not target for absence self-report.']);
        }
        if (!is_null($leave->handle_type)) {
            return back()->with('toast_errors', ['Already submitted.']);
        }

        $validated = $request->validated();

        $leave->update([
            'reason'      => $validated['reason'],
            'handle_type' => $validated['handle_type'],
        ]);

        // ★ 添付ファイルの保存処理（最小追加）
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');

            // 既存の添付があれば削除したい場合（任意）
            if ($leave->attachment) {
                Storage::disk('local')->delete($leave->attachment->path);
                $leave->attachment->delete();
            }

            // storage/app/attachments/YYYY/MM/... に保存（非公開）
            $path = $file->store('attachments/' . now()->format('Y/m'), 'local');

            $leave->attachment()->create([
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'size'          => $file->getSize(),
            ]);
        }

        return back()->with('toast', 'Submitted.');
    }

    /**
     * 欠席報告一覧（管理者用）
     * GET /leaves/all-report — status/kind/from/to/user_id でフィルタ、20件ページネーション
     */
    public function allReport(AllReportRequest $request)
    {
        $this->authorize('viewAny', Leave::class);
        $viewer = Auth::user();

        $validated = $request->validated();

        $status  = $validated['status'] ?? 'all';
        $kind    = $validated['kind']   ?? LeaveKind::Absence->value; // 既定は absence のみ
        $from    = $validated['from']   ?? null;
        $to      = $validated['to']     ?? null;
        $userId  = $validated['user_id'] ?? null;

        // 基本クエリ
        $kinds = $kind === 'all' ? LeaveKind::absenceReportValues() : [$kind];

        $q = Leave::query()
            ->with(['user:id,first_name,family_name,name,employee_code'])
            ->whereIn('user_id', $this->scopeService->targetUserIds())
            ->whereIn('kind', $kinds);

        if ($from) $q->whereDate('start_date', '>=', $from);
        if ($to)   $q->whereDate('start_date', '<=', $to);
        if ($userId) $q->where('user_id', $userId);

        // status フィルタ
        // needsReport = kind=absence && handle_type IS NULL
        if ($status === 'required') {
            $q->where('kind', LeaveKind::Absence->value)->whereNull('handle_type');
        } elseif ($status === 'submitted') {
            $q->whereNotNull('handle_type');
        }

        $leaves = $q->orderByDesc('start_date')->paginate(20)->appends($request->query());

        // ラベル
        $kindLabels = LeaveKind::absenceReportLabels();

        // Handle Type の候補（ラベル=保存値運用にも対応）
        $handleTypeOptions = [
            'I will apply for an ALP via HR Brain for this date of absence.',
            'I will not use an ALP for this absence (non-paid absence).',
            'I will use Sick or Injured Child Care Leave for this absence.',
            'I will use Special Leave for this absence.',
            'I will use Menstrual Leave for this absence.',
        ];
        // 表示用の「値→表示」辞書（保存値=ラベル運用にも対応）
        $handleTypeDict = array_combine($handleTypeOptions, $handleTypeOptions);

        // 行ビュー用の派生値を付与
        $rows = $leaves->getCollection()->map(function (Leave $leave) use ($kindLabels, $handleTypeDict) {
            $kindLabel = $leave->kind === LeaveKind::Other->value
                ? ($leave->special_type ?: 'Others')
                : ($kindLabels[$leave->kind] ?? ucfirst($leave->kind));

            $needsReport = ($leave->kind === LeaveKind::Absence->value) && is_null($leave->handle_type);

            $statusText = $leave->handle_type
                ? 'Submitted'
                : ($needsReport ? 'Required' : '—');

            $dateMain = optional($leave->start_date)->toDateString();
            $dateSub  = (!empty($leave->end_date) && $leave->end_date && $leave->end_date->ne($leave->start_date))
                ? $leave->end_date->toDateString()
                : null;

            $handleLabel = $leave->handle_type
                ? ($handleTypeDict[$leave->handle_type] ?? $leave->handle_type) // 保存値=ラベル運用でもOK
                : '';

            $userName = trim(($leave->user->family_name ?? '') . ' ' . ($leave->user->first_name ?? ''))
                ?: ($leave->user->name ?? 'User #' . $leave->user_id);

            return [
                'leave'        => $leave,
                'userName'     => $userName,
                'employeeCode' => $leave->user->employee_code ?? null,
                'kindLabel'    => $kindLabel,
                'needsReport'  => $needsReport,
                'statusText'   => $statusText,
                'dateMain'     => $dateMain,
                'dateSub'      => $dateSub,
                'handleLabel'  => $handleLabel,
            ];
        });

        // ページネータのコレクションを差し替え
        $leaves->setCollection($rows);

        // ユーザー選択用（任意）
        $userOptions = $this->scopeService->targetUserQuery()->orderBy('family_name')->orderBy('first_name')->get(['id', 'family_name', 'first_name', 'employee_code']);

        return view('calendar.absenceReportAll', [
            'viewer'             => $viewer,
            'leaves'             => $leaves,        // ページネータ（中身は rows）
            'kindLabels'         => $kindLabels,
            'handleTypeOptions'  => $handleTypeOptions,
            'userOptions'        => $userOptions,
            'filters'            => compact('status', 'kind', 'from', 'to', 'userId'),
        ]);
    }

    /**
     * 休暇添付ファイルのダウンロード
     * GET /leaves/{leave}/download — 非公開ストレージから元ファイル名でダウンロード
     */
    public function download(Leave $leave)
    {
        $this->authorize('download', $leave);

        $attachment = $leave->attachment;
        if (!$attachment) {
            abort(404);
        }

        $downloadName = $attachment->original_name ?: 'attachment';

        return Storage::disk('local')->download($attachment->path, $downloadName);
    }
}
