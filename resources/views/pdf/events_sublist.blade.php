<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 12mm;
        }

        body {
            font-family: 'Noto Sans JP', sans-serif;
            font-size: 10pt;
        }

        h1 {
            font-size: 14pt;
            margin: 0 0 3mm;
        }

        .meta {
            margin-bottom: 4mm;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 2px 3px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
        }

        /* ★Classes 列の最小幅 */
        th.col-classes,
        td.col-classes {
            min-width: 50mm;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
    </style>
</head>

<body>
    @php
    $mode = $meta['mode'] ?? 'tentative';
    $titleMap = [
    'tentative' => 'Tentative Sublist',
    'final' => 'Final Sublist',
    'master' => 'Master Sublist',
    ];
    $title = $titleMap[$mode] ?? 'Tentative';
    $fmtTime = function($t) {
    // $t が 'HH:MM:SS' 文字列でも DateTime でも最初の5文字を使う簡易フォーマット
    if (!$t) return '';
    return is_string($t) ? substr($t, 0, 5) : optional($t)->format('H:i');
    };
    // ★追加：日付キー用フォーマッタ
    $fmtDate = function ($d) {
    if (!$d) return '';
    if ($d instanceof \DateTimeInterface) return $d->format('Y-m-d');
    return substr((string)$d, 0, 10); // '2025-11-13 00:00:00' → '2025-11-13'
    };
    @endphp

    <h1>{{ $title }}</h1>
    <div class="meta">
        Date:
        @if(!empty($meta['range_from'])) {{ $meta['range_from'] }} @endif
        @if(!empty($meta['range_to'])) ~ {{ $meta['range_to'] }} @endif
        {{-- Generated: {{ $meta['generated_at'] }} --}}
    </div>

    @php
    // ★追加：日付ごとにグルーピング
    $groupsByDate = collect($events)->groupBy(fn($e) => $fmtDate($e->event_date) ?: 'No Date');
    @endphp

    @forelse($groupsByDate as $ymd => $rows)
    {{-- 日付見出し（新規テーブルのタイトル） --}}
    <h2 style="font-size:12pt;margin:10mm 0 3mm 0;">{{ $ymd }}</h2>

    <table>
        {{-- … <thead> は Note 列を外し、Master のみ Status を残す --}}
        <thead>
            <tr>
                {{-- <th>Date</th> --}}
                <th>Title</th>
                <th>School</th>
                <th>Regular Teacher</th>
                <th>Substitute Teacher</th>
                <th>Start</th>
                <th>End</th>
                <th class="col-classes">Classes</th> {{-- ★クラス付与 --}}
                <th>Leave Type</th>
                <th>Shift Type</th>
                @if($mode === 'master')
                <th>Status</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php
            // Master の1行目の列数（Noteを除いた列数）
            $masterCols = 10; // 9 基本列 + Status=1
            @endphp

            @forelse($rows as $e)
            {{-- 1行目（共通情報） --}}
            <tr>
                <td>{{ $e->title }}</td>
                <td>{{ $e->school_name }}</td>
                <td>{{ optional($e->originalUser)->name }}</td>
                <td>
                    @if($e->assignedUser)
                    {{ $e->assignedUser->name }}
                    @if($mode === 'final') [{{ $e->assignedUser->employee_code }}] @endif
                    @endif
                </td>
                <td>{{ $fmtTime($e->start_time) }}</td>
                <td>{{ $fmtTime($e->end_time) }}</td>
                <td class="col-classes">{{ $e->Lesson }}</td> {{-- ★クラス付与 --}}
                <td>{{ $e->Leave_type }}</td>
                <td>{{ $e->type_label }}</td>
                @if($mode === 'master')
                <td>{{ $e->status }}</td>
                @endif
            </tr>

            {{-- 2行目（Note：Master のみ、全カラム結合） --}}
            @if($mode === 'master')
            <tr>
                <td colspan="{{ $masterCols }}" style="border-top:0; padding-top:10px;">
                    <strong>Note:</strong>
                    {{ trim((string) $e->notes) !== '' ? $e->notes : '—' }}
                </td>
            </tr>
            @endif
            @empty
            <tr>
                <td colspan="{{ $mode==='master' ? $masterCols : 9 }}">No records.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @empty
    <p>No records.</p>
    @endforelse
</body>

</html>