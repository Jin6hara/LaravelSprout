<?php
/**
 * 一般ユーザーによる有給・特別休暇の申請フォーム表示・申請送信を担うコントローラ。
 */
namespace App\Http\Controllers;

use App\Enums\LeaveKind;
use App\Http\Requests\LeaveApply\LeaveApplyRequest;
use App\Models\LeaveCredit;
use App\Models\User;
use App\Services\LeaveApply\LeaveApplicationService;
use App\Support\Fiscal;
use Illuminate\Http\RedirectResponse;

class LeaveApplyController extends Controller
{
    public function __construct(private LeaveApplicationService $leaveApplicationService) {}

    /**
     * 本人用 ALP 申請フォーム表示
     * GET /leave/apply/create
     */
    public function create()
    {
        $user   = auth()->user();
        $fy     = Fiscal::fyKey(now());
        $credit = LeaveCredit::where('user_id', $user->id)->where('fy', $fy)->first();

        return view('leaves.alpApply', [
            'action'    => route('leave.apply.store'),
            'remaining' => $credit?->remaining_days ?? 0,
            'fy'        => $fy,
        ]);
    }

    /**
     * 本人が送信（dates[]）
     */
    public function store(LeaveApplyRequest $request): RedirectResponse
    {
        $type           = (string) $request->input('type', LeaveKind::Paid->value);
        $specialType    = (string) $request->input('special_type', '');
        $attachmentMeta = $this->handleAttachment($type, $request);

        $result = $this->leaveApplicationService->apply(
            (int) auth()->id(),
            (array) $request->input('dates', []),
            (string) $request->input('reason', ''),
            (int) auth()->id(),
            $type,
            $specialType ?: null,
            $attachmentMeta
        );

        return redirect()->route('leave.apply.create')->with('toast', $this->buildToast($result));
    }

    /**
     * 管理者：対象ユーザーを指定してフォーム表示
     */
    public function createForUser(User $user)
    {
        $this->authorize('manage', $user);

        return view('leaves.alpApply', [
            'action'     => route('leave.apply.storeForUser', $user),
            'targetUser' => $user,
        ]);
    }

    /**
     * 管理者：対象ユーザーの分を送信（dates[]）
     */
    public function storeForUser(LeaveApplyRequest $request, User $user): RedirectResponse
    {
        $this->authorize('manage', $user);

        $type           = (string) $request->input('type', LeaveKind::Paid->value);
        $specialType    = (string) $request->input('special_type', '');
        $attachmentMeta = $this->handleAttachment($type, $request);

        $result = $this->leaveApplicationService->apply(
            $user->id,
            (array) $request->input('dates', []),
            (string) $request->input('reason', ''),
            (int) auth()->id(),
            $type,
            $specialType ?: null,
            $attachmentMeta
        );

        return redirect()->route('leave.apply.create')->with('toast', $this->buildToast($result));
    }

    /**
     * 特別休暇の添付ファイルを storage に保存してメタ情報を返す
     * 特別休暇以外、またはファイルなしの場合は null を返す
     */
    private function handleAttachment(string $type, LeaveApplyRequest $request): ?array
    {
        if ($type !== LeaveKind::Special->value || !$request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');
        $path = $file->store('attachments/' . now()->format('Y/m'), 's3');
        if ($path === false) {
            throw new \RuntimeException('ファイルのアップロードに失敗しました。');
        }

        return [
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'size'          => $file->getSize(),
        ];
    }

    /**
     * 申請結果をトーストメッセージ文字列に整形
     * created/skipped 件数とスキップ理由を含む
     */
    private function buildToast(array $result): string
    {
        $created = $result['created'];
        $skipped = $result['skipped'];

        // ※「有給申請」→「休暇申請」に文言を少し広げる
        $msg = "休暇申請を送信しました（作成: {$created}件";
        if ($skipped > 0) {
            $msg .= " / スキップ: {$skipped}件";
        }
        $msg .= '）。';

        if (!empty($result['skips'])) {
            $detail = collect($result['skips'])
                ->map(fn($why, $d) => "{$d}: {$why}")
                ->join(' / ');
            $msg .= ' ' . $detail;
        }

        return $msg;
    }
}
