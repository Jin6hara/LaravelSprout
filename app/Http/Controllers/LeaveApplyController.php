<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveApplyRequest;
use App\Models\User;
use App\Models\Leave;
use App\Models\ApprovalRequest;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Models\LeaveCredit;
use App\Support\Fiscal;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class LeaveApplyController extends Controller
{
    /**
     * 本人用 申請フォーム
     */
    public function create()
    {
        $user = auth()->user();
        $fy = Fiscal::fyKey(now());
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
        // ★ 申請タイプ & 添付処理
        $type = (string) $request->input('type', 'paid'); // paid|special
        $specialType = (string) $request->input('special_type', '');

        $attachmentMeta = null;
        if ($type === 'special' && $request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('attachments/' . now()->format('Y/m'), 'public');

            $attachmentMeta = [
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'size'          => $file->getSize(),
            ];
        }

        return $this->applyCore(
            (int) $request->input('user_id'),
            (array) $request->input('dates', []),
            (string) $request->input('reason', ''),
            (int) auth()->id(),
            $type,
            $specialType,
            $attachmentMeta
        );
    }

    /**
     * 管理者：対象ユーザーを指定してフォーム表示
     */
    public function createForUser(User $user)
    {
        $this->authorize('manage', $user); // 任意（Policyがあれば）
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
        $this->authorize('manage', $user); // 任意

        // ★ 申請タイプ & 添付処理（管理者経由でも同じ）
        $type = (string) $request->input('type', 'paid'); // paid|special
        $specialType = (string) $request->input('special_type', '');

        $attachmentMeta = null;
        if ($type === 'special' && $request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('attachments/' . now()->format('Y/m'), 'public');

            $attachmentMeta = [
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'size'          => $file->getSize(),
            ];
        }

        return $this->applyCore(
            (int) $user->id,
            (array) $request->input('dates', []),
            (string) $request->input('reason', ''),
            (int) auth()->id(),
            $type,
            $specialType,
            $attachmentMeta
        );
    }

    /**
     * コア処理：
     * - dates[] をユニーク＆昇順
     * - 各日付ごとに Leave(pending, paid/special) + ApprovalRequest を作成
     * - 既存の pending/approved がある日はスキップ
     * - 管理者へ通知
     * - 結果サマリをフラッシュ
     */
    private function applyCore(
        int $userId,
        array $dates,
        string $reason,
        int $requestedByUserId,
        string $type = 'paid',
        ?string $specialType = null,
        ?array $attachmentMeta = null
    ): RedirectResponse {
        // 事前整形：null/空白を除去 → 重複除去 → 昇順
        $dates = collect($dates)
            ->map(fn($d) => trim((string) $d))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return back()->withErrors(['dates' => '申請日を1つ以上入力してください。'])->withInput();
        }

        $result = [
            'created' => 0,
            'skipped' => 0,
            'skips'   => [], // ['2025-09-10' => 'already requested', ...]
        ];

        DB::transaction(function () use ($userId, $dates, $reason, $requestedByUserId, $type, $specialType, $attachmentMeta, &$result) {
            foreach ($dates as $ymd) {
                // --- 重複チェック：同日に paid leave の pending/approved があるか ---
                $already = Leave::query()
                    ->where('user_id', $userId)
                    ->where('kind', 'paid')
                    ->whereIn('status', ['pending', 'approved'])
                    ->whereDate('start_date', $ymd)
                    ->exists();

                if ($already) {
                    $result['skipped']++;
                    $result['skips'][$ymd] = '既に申請/承認済みです';
                    continue;
                }

                // --- Leave を pending で作成（単日） ---
                $leave = Leave::create([
                    'user_id'      => $userId,
                    'start_date'   => $ymd,
                    'end_date'     => null,
                    'kind'         => $type === 'special' ? 'special' : 'paid',
                    'excused'      => 'excused',
                    'reason'       => $reason,
                    'status'       => 'pending',
                    'special_type' => $type === 'special' ? ($specialType ?: null) : null,
                ]);

                // ★ special の場合は添付レコードを作成（同じファイルを各日分に紐付け）
                if ($type === 'special' && $attachmentMeta) {
                    $leave->attachment()->create($attachmentMeta);
                }

                // --- 承認リクエスト（ポリモーフィック） ---
                $ar = $leave->approvalRequest()->create([
                    'title'           => sprintf('特別休暇申請: user#%d %s', $userId, $ymd),
                    'requested_by_id' => $requestedByUserId,
                    'current_state'   => 'pending',
                    'metadata'        => [
                        'leave_id'      => $leave->id,
                        'user_id'       => $userId,
                        'date'          => $ymd,
                        'kind'          => $type,      // ★ paid or special
                        'reason'        => $reason,
                        'special_type'  => $type === 'special' ? ($specialType ?: null) : null,
                    ],
                ]);

                // --- 通知（管理者以上へ）---
                $admins = \App\Models\User::role(['admin', 'super_admin'])->get();
                Notification::send($admins, new \App\Notifications\ApprovalRequestedNotification($ar));

                $result['created']++;
            }
        });

        // フィードバック文面
        $msg = "有給申請を送信しました（作成: {$result['created']}件";
        if ($result['skipped'] > 0) {
            $msg .= " / スキップ: {$result['skipped']}件";
        }
        $msg .= '）。';

        // スキップ詳細を併せて表示したい場合
        if (!empty($result['skips'])) {
            $detail = collect($result['skips'])
                ->map(fn($why, $d) => "{$d}: {$why}")
                ->join(' / ');
            $msg .= ' ' . $detail;
        }

        return redirect()->route('leave.apply.create')->with('toast', $msg);
    }
}
