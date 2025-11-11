<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 14mm;
        }

        body {
            font-family: 'Noto Sans JP', sans-serif;
            font-size: 10pt;
        }

        h1 {
            font-size: 14pt;
            margin: 0 0 6mm;
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
            padding: 4px 6px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
        }
    </style>
</head>

<body>
    @php
    $mode = $meta['mode'] ?? 'tentative';
    $titleMap = [
    'tentative' => 'Tentative',
    'final' => 'Final Sublist',
    'master' => 'Master Sublist',
    ];
    $title = $titleMap[$mode] ?? 'Tentative';
    $fmtTime = function($t) {
    // $t が 'HH:MM:SS' 文字列でも DateTime でも最初の5文字を使う簡易フォーマット
    if (!$t) return '';
    return is_string($t) ? substr($t, 0, 5) : optional($t)->format('H:i');
    };
    @endphp

    <h1>{{ $title }}</h1>
    <div class="meta">
        期間:
        @if(!empty($meta['range_from'])) {{ $meta['range_from'] }} @endif
        @if(!empty($meta['range_to'])) 〜 {{ $meta['range_to'] }} @endif
        ／ 生成: {{ $meta['generated_at'] }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>School</th>
                <th>Original User</th>
                <th>Assigned User</th>
                <th>Start</th>
                <th>End</th>
                <th>Lessons</th>
                <th>Leave Type</th>
                <th>Type</th>
                @if($mode === 'master')
                <th>Note</th>
                <th>Status</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($events as $e)
            <tr>
                <td>{{ $e->event_date }}</td>
                <td>{{ $e->school_name }}</td>

                {{-- Original User --}}
                <td>
                    @if($e->originalUser)
                    {{ $e->originalUser->name }}
                    @endif
                </td>

                {{-- Assigned User（final は employee_code を併記） --}}
                <td>
                    @if($e->assignedUser)
                    {{ $e->assignedUser->name }}
                    @if($mode === 'final')
                    [{{ $e->assignedUser->employee_code }}]
                    @endif
                    @endif
                </td>

                <td>{{ $fmtTime($e->start_time) }}</td>
                <td>{{ $fmtTime($e->end_time) }}</td>
                <td>{{ $e->Lesson }}</td>
                <td>{{ $e->Leave_type }}</td>
                <td>{{ $e->type }}</td>

                @if($mode === 'master')
                <td>{{ $e->notes }}</td>
                <td>{{ $e->status }}</td>
                @endif
            </tr>
            @empty
            <tr>
                <td colspan="{{ $mode==='master' ? 11 : 9 }}">No records.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>