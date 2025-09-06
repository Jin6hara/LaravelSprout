<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleDetailSeeder extends Seeder
{
    public function run(): void
    {
        // --- 前提データ読み込み ---------------------------------------------
        // lesson_start_times: 'H:i' => id
        $startIdByHm = DB::table('lesson_start_times')
            ->get()
            ->mapWithKeys(fn($r) => [substr($r->start_time,0,5) => $r->id])
            ->toArray();

        // lessons: id => minute
        $lessonMinById = DB::table('lessons')
            ->get()
            ->mapWithKeys(fn($r) => [$r->id => (int)$r->lesson_minute])
            ->toArray();

        // schedule_lines: id => ['start'=>'H:i','end'=>'H:i']
        $lines = DB::table('schedule_lines')->get()
            ->mapWithKeys(fn($r) => [
                $r->id => [
                    'start' => substr($r->start_time,0,5),
                    'end'   => substr($r->end_time,0,5),
                ]
            ])
            ->toArray();

        if (empty($lines) || empty($startIdByHm) || empty($lessonMinById)) {
            $this->command?->warn('Required base tables missing. Skip.');
            return;
        }

        // --- ユーティリティ ---------------------------------------------------
        $toMin = function(string $hm): int {
            [$H,$M] = array_map('intval', explode(':',$hm));
            return $H*60 + $M;
        };
        $toHm = function(int $m): string {
            $H = intdiv($m,60); $M = $m%60;
            return sprintf('%02d:%02d',$H,$M);
        };
        $round5 = fn(int $m): int => (int) round($m/5)*5;

        // line帯の中から、lesson分を消化できる“合法スタート”スロット群を作成
        $makeLegalSlots = function(string $lineStartHm, string $lineEndHm, int $lessonMin) use ($toMin,$toHm,$round5,$startIdByHm) {
            $ls = $toMin($lineStartHm);
            $le = $toMin($lineEndHm);
            if ($le <= $ls) return [];

            // lesson 分だけ余白を残した「最終開始可能時刻」
            $lastStart = $le - $lessonMin;
            if ($lastStart < $ls) return []; // 物理的に入らない

            // 5分網羅（lineStart..lastStart）
            $slots = [];
            for ($m = $round5($ls); $m <= $round5($lastStart); $m += 5) {
                $hm = $toHm($m);
                if (isset($startIdByHm[$hm])) $slots[] = $hm; // テーブルに存在する時刻のみ
            }
            return $slots;
        };

        // 等間隔に n 個抜き出し（端含む）
        $pickEven = function(array $arr, int $n): array {
            $cnt = count($arr);
            if ($n <= 0 || $cnt === 0) return [];
            if ($n >= $cnt) return $arr;
            $idxs = [];
            for ($i=0;$i<$n;$i++) {
                $idxs[] = (int) round($i*($cnt-1)/max(1,$n-1));
            }
            $idxs = array_values(array_unique($idxs));
            while (count($idxs) < $n) { // もし重複で減ったら穴埋め
                for ($k=0;$k<$cnt && count($idxs)<$n;$k++) {
                    if (!in_array($k,$idxs,true)) $idxs[] = $k;
                }
            }
            sort($idxs);
            return array_map(fn($i)=>$arr[$i], $idxs);
        };

        // 1行分をまとめて投入
        $insertDetails = function (int $lineId, array $startsHm, array $lessonIds) use ($startIdByHm) {
            $n = min(count($startsHm), count($lessonIds));
            $rows = [];
            for ($i=0; $i<$n; $i++) {
                $hm = $startsHm[$i];
                $startId = $startIdByHm[$hm] ?? null;
                if (!$startId) continue;
                $rows[] = [
                    'schedule_line_id'     => $lineId,
                    'lesson_start_time_id' => $startId,
                    'lesson_id'            => $lessonIds[$i],
                ];
            }
            if ($rows) DB::table('schedule_details')->insertOrIgnore($rows);
        };

        // --- 指定（ID 群と本数・対応するレッスンID） -------------------------
        $specs = [
            [['1','2','13'], 6, [1,2,3,4,5,6]],
            [['6','10','12'], 6, [1,2,3,4,5,6]],
            [['3','9'],       6, [1,2,3,4,5,6]],
            [['4'],           3, [1,2,3]],
            [['5'],           2, [4,5]],
        ];

        foreach ($specs as [$lineIds, $count, $lessonIds]) {
            foreach ($lineIds as $lineIdStr) {
                $lineId = (int)$lineIdStr;
                if (!isset($lines[$lineId])) continue;

                $lineStart = $lines[$lineId]['start']; // 'H:i'
                $lineEnd   = $lines[$lineId]['end'];

                // 各ペア（start×lesson）ごとに“合法スロット”を作成し公平に配分
                // ここでは全 lessonIds の「平均的な分数」を使ってスロット候補を作り、
                // 実際の lesson 分数で最終チェックする（厳格にするなら1つずつ再計算でもOK）
                $avgMin = (int) round(array_sum(array_map(fn($id)=>$lessonMinById[$id] ?? 0, $lessonIds)) / max(1,count($lessonIds)));
                $candidates = $makeLegalSlots($lineStart, $lineEnd, $avgMin);

                if (empty($candidates)) {
                    // 平均で入らない場合は最小分のレッスンに合わせて再挑戦
                    $minMin = min(array_map(fn($id)=>$lessonMinById[$id] ?? 999, $lessonIds));
                    $candidates = $makeLegalSlots($lineStart, $lineEnd, $minMin);
                }

                if (empty($candidates)) continue; // そもそも入らない

                $starts = $pickEven($candidates, $count);

                // 実分数で最終フィルタ（start + lesson_min <= line_end）
                $lineEndMin = $toMin($lineEnd);
                $filteredStarts = [];
                for ($i=0; $i<count($starts) && $i<count($lessonIds); $i++) {
                    $hm = $starts[$i];
                    $need = $lessonMinById[$lessonIds[$i]] ?? 0;
                    if ($need <= 0) continue;
                    if ($toMin($hm) + $need <= $lineEndMin) {
                        $filteredStarts[] = $hm;
                    }
                }

                // それでも不足するなら、超安全側に**手前に寄せて**詰める
                while (count($filteredStarts) < min($count, count($lessonIds))) {
                    // 直前の5分へずらす
                    $wantIndex = count($filteredStarts);
                    $hm = $starts[$wantIndex] ?? end($starts) ?: $lineStart;
                    $m  = $toMin($hm);
                    $need = $lessonMinById[$lessonIds[$wantIndex]] ?? 0;

                    // ずらしながら探す（最大 60分ぶん）
                    $ok = false;
                    for ($back=5; $back<=60; $back+=5) {
                        $candidate = $m - $back;
                        if ($candidate < $toMin($lineStart)) break;
                        if ($candidate + $need <= $lineEndMin) {
                            $candHm = $toHm($candidate);
                            if (isset($startIdByHm[$candHm])) { $filteredStarts[] = $candHm; $ok=true; break; }
                        }
                    }
                    if (!$ok) break;
                }

                // ペアリングしてINSERT
                $thisIds = array_slice($lessonIds, 0, min(count($filteredStarts), count($lessonIds)));
                $insertDetails($lineId, $filteredStarts, $thisIds);
            }
        }
    }
}
