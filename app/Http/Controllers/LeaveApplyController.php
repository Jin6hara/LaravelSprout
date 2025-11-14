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
use Illuminate\Support\Str;

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
     * - 有給(paid)：各日付ごとに Leave(pending, paid) + ApprovalRequest
     * - 特別休暇(special)：期間で 1 Leave + 1 ApprovalRequest
     * - 既存の pending/approved がある日は/期間はスキップ
     * - Notification は「1申請につき 1件」だけ送信
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

        // ★ 1申請を識別するバッチID
        $batchId = (string) Str::uuid();

        // ★ モード別に処理
        if ($type === 'special') {
            $result = $this->applySpecial(
                $userId,
                $dates,
                $reason,
                $requestedByUserId,
                $specialType,
                $attachmentMeta,
                $batchId
            );
        } else {
            $result = $this->applyPaid(
                $userId,
                $dates,
                $reason,
                $requestedByUserId,
                $batchId
            );
        }

        // フィードバック文面
        $created = $result['created'] ?? 0;
        $skipped = $result['skipped'] ?? 0;

        // ※「有給申請」→「休暇申請」に文言を少し広げる
        $msg = "休暇申請を送信しました（作成: {$created}件";
        if ($skipped > 0) {
            $msg .= " / スキップ: {$skipped}件";
        }
        $msg .= '）。';

        // スキップ詳細を併せて表示したい場合
        if (!empty($result['skips'] ?? [])) {
            $detail = collect($result['skips'])
                ->map(fn($why, $d) => "{$d}: {$why}")
                ->join(' / ');
            $msg .= ' ' . $detail;
        }

        // ★ Notification を 1申請につき 1回だけ送信
        $requests = $result['requests'] ?? [];
        if (!empty($requests)) {
            $firstRequest = $requests[0];
            $totalCount   = count($requests);

            $admins = User::role(['super_admin'])->get();
            Notification::send(
                $admins,
                new \App\Notifications\ApprovalRequestedNotification(
                    $firstRequest,
                    $totalCount,
                    $batchId
                )
            );
        }

        return redirect()->route('leave.apply.create')->with('toast', $msg);
    }

    /**
     * 有給（paid）用：1日1レコード
     *
     * @return array{created:int,skipped:int,skips:array,requests:array}
     */
    private function applyPaid(
        int $userId,
        \Illuminate\Support\Collection $dates,
        string $reason,
        int $requestedByUserId,
        string $batchId
    ): array {
        $result = [
            'created'  => 0,
            'skipped'  => 0,
            'skips'    => [],
            'requests' => [],
        ];

        // ★ ユーザー名 + [employee_code] と、全日付のまとめ文字列を作っておく
        $user = User::find($userId);
        if ($user) {
            $code = $user->employee_code ?? '';
            $userLabel = $code
                ? "{$user->name} [{$code}]"
                : $user->name;
        } else {
            $userLabel = "user#{$userId}";
        }

        $dateListStr = $dates->implode(', '); // 例: 2025-11-01, 2025-11-02
        $typeLabel   = '**ALP**';

        // ★ approval_requests.title に入れる共通タイトル
        //    例: 「有給（ALP） 山田太郎 [000123] (2025-11-01, 2025-11-02)」
        $titleBase = "{$typeLabel} {$userLabel} ({$dateListStr})";

        DB::transaction(function () use ($userId, $dates, $reason, $requestedByUserId, $batchId, $titleBase, &$result) {
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
                    'kind'         => 'paid',
                    'excused'      => 'excused',
                    'reason'       => $reason,
                    'status'       => 'pending',
                    'special_type' => null,
                ]);

                // --- 承認リクエスト（ポリモーフィック） ---
                $ar = $leave->approvalRequest()->create([
                    // ★ 全 ApprovalRequest に同じ「まとめタイトル」を設定
                    'title'           => $titleBase,
                    'requested_by_id' => $requestedByUserId,
                    'current_state'   => 'pending',
                    'metadata'        => [
                        'batch_id'     => $batchId,
                        'leave_id'     => $leave->id,
                        'user_id'      => $userId,
                        'date'         => $ymd,
                        'kind'         => 'paid',
                        'reason'       => $reason,
                        'special_type' => null,
                    ],
                ]);

                $result['created']++;
                $result['requests'][] = $ar;
            }
        });

        return $result;
    }

    /**
     * 特別休暇（special）用：期間1レコード
     *
     * @return array{created:int,skipped:int,skips:array,requests:array}
     */
    private function applySpecial(
        int $userId,
        \Illuminate\Support\Collection $dates,
        string $reason,
        int $requestedByUserId,
        ?string $specialType,
        ?array $attachmentMeta,
        string $batchId
    ): array {
        $result = [
            'created'  => 0,
            'skipped'  => 0,
            'skips'    => [],
            'requests' => [],
        ];

        // ★ ユーザー名 + [employee_code]
        $user = User::find($userId);
        if ($user) {
            $code = $user->employee_code ?? '';
            $userLabel = $code
                ? "{$user->name} [{$code}]"
                : $user->name;
        } else {
            $userLabel = "user#{$userId}";
        }
        $typeLabel = '**Special Leave**';

        DB::transaction(function () use ($userId, $dates, $reason, $requestedByUserId, $specialType, $attachmentMeta, $batchId, $userLabel, $typeLabel, &$result) {
            $start = $dates->min();
            $end   = $dates->max();

            if (!$start || !$end) {
                return;
            }

            // ★ 期間文字列を作成（例: 2025-11-10〜2025-11-12）
            $periodStr = "{$start}〜{$end}";
            // ★ approval_requests.title に入れるタイトル
            //    例: 「特別休暇 山田太郎 [000123] (2025-11-10〜2025-11-12)」
            $titleBase = "{$typeLabel} {$userLabel} ({$periodStr})";

            // --- 重複チェック：同ユーザーで special の pending/approved が期間重複していないか ---
            $already = Leave::query()
                ->where('user_id', $userId)
                ->where('kind', 'special')
                ->whereIn('status', ['pending', 'approved'])
                ->where(function ($q) use ($start, $end) {
                    // start_date～end_date が少しでも重なればNG
                    $q->whereBetween('start_date', [$start, $end])
                        ->orWhereBetween('end_date', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->where('start_date', '<=', $start)
                                ->where('end_date', '>=', $end);
                        });
                })
                ->exists();

            if ($already) {
                $result['skipped'] = 1;
                $result['skips']["{$start}〜{$end}"] = '既に申請/承認済みです';
                return;
            }

            // --- Leave（期間）を1レコード作成 ---
            $leave = Leave::create([
                'user_id'      => $userId,
                'start_date'   => $start,
                'end_date'     => $end,
                'kind'         => 'special',
                'excused'      => 'excused',
                'reason'       => $reason,
                'status'       => 'pending',
                'special_type' => $specialType ?: null,
            ]);

            // ★ 添付（証明書）があれば 1:1 で紐付け
            if ($attachmentMeta) {
                $leave->attachment()->create($attachmentMeta);
            }

            // --- 承認リクエスト（ポリモーフィック） ---
            $ar = $leave->approvalRequest()->create([
                // ★ 「特別休暇 ユーザー名 [code] (期間)」
                'title'           => $titleBase,
                'requested_by_id' => $requestedByUserId,
                'current_state'   => 'pending',
                'metadata'        => [
                    'batch_id'     => $batchId,
                    'leave_id'     => $leave->id,
                    'user_id'      => $userId,
                    'date_from'    => $start,
                    'date_to'      => $end,
                    'kind'         => 'special',
                    'reason'       => $reason,
                    'special_type' => $specialType ?: null,
                ],
            ]);

            $result['created']    = 1;
            $result['requests'][] = $ar;
        });

        return $result;
    }
}
