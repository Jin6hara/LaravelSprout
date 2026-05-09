<?php

namespace App\Http\Controllers;

use App\Services\CurrentScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\DatabaseText;
use App\Support\SchoolName;
use App\Support\TimeString;

class CommuterPassAdvisorController extends Controller
{
    public function index(Request $request)
    {
        // --- 入力値（画面再表示用） ---
        $inFrom = $request->query('from');
        $inTo   = $request->query('to');

        // --- 内部ロジック用（正規化＋デフォルト） ---
        $from = ($inFrom !== null && $inFrom !== '')
            ? TimeString::normalizeToYmd($inFrom)
            : now()->toDateString(); // デフォルト: 本日

        $to = ($inTo !== null && $inTo !== '')
            ? TimeString::normalizeToYmd($inTo)
            : null; // null = 上限なし（from 以降）

        $min = (int)($request->query('min_count') ?: 5);
        $targetUserIds = app(CurrentScopeService::class)->targetUserIds();
        $text = DB::query();
        $slSchoolKeyExpr = DatabaseText::loweredExpression($text, 'sl.school_name');
        $baseSchoolKeyExpr = DatabaseText::loweredExpression($text, 'base.school_name');
        $peerSchoolKeyExpr = DatabaseText::loweredExpression($text, 'peer.school_name');

        // --- 共通: 期間条件を適用するクロージャ ---
        $applyPeriod = function ($q) use ($from, $to) {
            if ($from && !$to) {
                // from のみ： end >= from
                $q->whereDate('sl.effective_end', '>=', $from);
            } elseif (!$from && $to) {
                // to のみ： start <= to
                $q->whereDate('sl.effective_start', '<=', $to);
            } else {
                // 両方（from=デフォルト本日を含む）： start <= to AND end >= from
                $q->whereDate('sl.effective_start', '<=', $to)
                    ->whereDate('sl.effective_end', '>=', $from);
            }
            return $q;
        };

        // --- 1) 候補グループ: user_id × school_name ごとの件数（期間重複あり、min 以上） ---
        $groupsSub = DB::table('schedule_lines as sl')
            ->whereIn('sl.user_id', $targetUserIds)
            ->whereNotNull('sl.user_id')
            ->tap($applyPeriod)
            ->groupBy('sl.user_id', DB::raw($slSchoolKeyExpr))
            ->havingRaw('COUNT(*) >= ?', [$min])
            ->select([
                'sl.user_id',
                DB::raw('MIN(sl.school_name) as school_name'),
                DB::raw("{$slSchoolKeyExpr} as school_name_key"),
                DB::raw('COUNT(*) as cnt'),
            ]);

        // 画面上部で使う一覧
        $groups = DB::query()
            ->fromSub($groupsSub, 'g')
            ->orderBy('g.user_id')
            ->orderByDesc('g.cnt')
            ->get(); // => user_id, school_name, cnt

        // ▼▼▼▼ 明細取得ロジック：対象外の詳細は含まない ▼▼▼▼

        // --- 2) 明細のための「行ごとに同時稼働曜日数を数える」サブクエリ ---
        // base 行と同一 user & 同一 school_name の peer 行と期間が重なる曜日を DISTINCT で数える
        // ※ 同一ユーザー内で同一 school_name をまたいで schedule_id が分かれる可能性があるなら
        // schedule_id 同士の一致縛りは外し、user_id で合わせるのが安全。
        $qualSub = DB::table('schedule_lines as base')
            ->whereIn('base.user_id', $targetUserIds)
            ->whereNotNull('base.user_id')
            ->join('schedule_lines as peer', function ($join) use ($baseSchoolKeyExpr, $peerSchoolKeyExpr) {
                $join->whereRaw("{$peerSchoolKeyExpr} = {$baseSchoolKeyExpr}")
                    ->on('peer.user_id', '=', 'base.user_id');
            })
            // base と peer の期間が重なる
            ->whereColumn('peer.effective_start', '<=', 'base.effective_end')
            ->whereColumn('peer.effective_end', '>=', 'base.effective_start')
            // 検索期間とも重なる（base 側で代表させる）
            ->when(true, function ($q) use ($from, $to) {
                if ($from && !$to) {
                    $q->whereDate('base.effective_end', '>=', $from);
                } elseif (!$from && $to) {
                    $q->whereDate('base.effective_start', '<=', $to);
                } else {
                    $q->whereDate('base.effective_start', '<=', $to)
                        ->whereDate('base.effective_end', '>=', $from);
                }
            })
            ->groupBy('base.id')
            ->selectRaw('base.id as line_id, COUNT(DISTINCT peer.dow) as dow_cnt');

        // --- 3) 明細: groupsSub（候補グループ）と qualSub（同時稼働曜日数）を join して抽出 ---
        $details = DB::table('schedule_lines as sl')
            ->whereIn('sl.user_id', $targetUserIds)
            ->whereNotNull('sl.user_id')
            ->joinSub($groupsSub, 'g', function ($join) use ($slSchoolKeyExpr) {
                $join->on('g.user_id', '=', 'sl.user_id')
                ->whereRaw("g.school_name_key = {$slSchoolKeyExpr}");
            })
            ->joinSub($qualSub, 'qual', function ($join) {
                $join->on('qual.line_id', '=', 'sl.id');
            })
            ->where('qual.dow_cnt', '>=', $min)
            ->tap($applyPeriod)
            ->select([
                'sl.user_id',
                'sl.id as line_id',
                'sl.school_name',
                DB::raw("{$slSchoolKeyExpr} as school_name_key"),
                'sl.dow',
                'sl.effective_start',
                'sl.effective_end',
            ])
            ->orderBy('sl.user_id')
            ->orderByRaw($slSchoolKeyExpr)
            ->orderBy('sl.dow')
            ->get();

        $details = $details->unique(
            fn($r) =>
            $r->user_id . '_' . $r->school_name_key . '_' . $r->dow . '_' . $r->effective_start . '_' . $r->effective_end
        )->values();
        // ▲▲▲▲ 明細取得ロジック：対象外の詳細は含まない ▲▲▲▲

        // --- 4) 画面用に（user_id → school_name）でグルーピング ---
        $grouped = $groups->groupBy('user_id')->map(function ($rows, $uid) use ($details) {
            return $rows->keyBy('school_name_key')->map(function ($row, $schoolKey) use ($details, $uid) {
                $lines = $details->where('user_id', $uid)->where('school_name_key', $schoolKey)->values();
                return [
                    'school_name' => SchoolName::normalize($row->school_name) ?? $row->school_name,
                    'count'       => $row->cnt,
                    'lines'       => $lines,
                ];
            });
        });

        // 追加：表示に必要なユーザー情報を取得
        $userIds = $groups->pluck('user_id')->unique()->values();
        $userMap = DB::table('users')
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'family_name', 'employee_code'])
            ->keyBy('id');

        // 追加: この画面に出てくるユーザーの定期券だけ取得
        // 定期券の期間条件（from/toの解釈は schedule_lines と同じ）
        $applyPassPeriod = function ($q) use ($from, $to) {
            if ($from && !$to) {
                // fromのみ：定期が from 以降に一部でもかかる
                $q->whereDate('date_to', '>=', $from);
            } elseif (!$from && $to) {
                // toのみ：定期が to 以前に一部でもかかる
                $q->whereDate('date_from', '<=', $to);
            } else {
                // 両方（from=本日デフォルト含む）：重なり判定
                $q->whereDate('date_from', '<=', $to)
                    ->whereDate('date_to', '>=', $from);
            }
            return $q;
        };

        $passes = DB::table('commuter_passes')
            ->whereIn('user_id', $userIds)
            ->tap($applyPassPeriod)
            ->orderBy('user_id')
            ->orderBy('date_from')
            ->get();

        // ユーザー毎にまとめる
        $passesMap = $passes->groupBy('user_id');

        // --- フォーム表示値（old/request を優先） ---
        $viewFrom = ($inFrom !== null && $inFrom !== '') ? $inFrom : $from;
        $viewTo   = ($inTo !== null && $inTo !== '') ? $inTo   : '';

        return view('expenses.passAdvisor', [
            'from'       => $viewFrom,
            'to'         => $viewTo,
            'min'        => $min,
            'grouped'    => $grouped,
            'userMap'    => $userMap,
            'passesMap'  => $passesMap,
        ]);
    }
}
