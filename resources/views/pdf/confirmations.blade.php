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

        h2 {
            font-size: 12pt;
            margin: 10mm 0 3mm 0;
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

        /* ★ Classes 列の最小幅 */
        th.col-classes,
        td.col-classes {
            min-width: 36mm;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
    </style>
</head>

<body>
    @php
    $mode = $meta['mode'] ?? 'alp';
    $title = $meta['title'] ?? ($mode === 'ot' ? 'OT Confirmation List' : 'ALP Confirmation List');

    $fmtTime = function($t) {
    if (!$t) return '';
    return is_string($t) ? substr($t, 0, 5) : optional($t)->format('H:i');
    };
    $fmtDate = function ($d) {
    if (!$d) return '';
    if ($d instanceof \DateTimeInterface) return $d->format('Y-m-d');
    return substr((string)$d, 0, 10);
    };

    // 日付ごとにグルーピング
    $groupsByDate = collect($events)->groupBy(fn($e) => $fmtDate($e->event_date) ?: 'No Date');
    @endphp

    <h1>{{ $title }}</h1>
    <div class="meta">
        Date:
        @if(!empty($meta['range_from'])) {{ $meta['range_from'] }} @endif
        @if(!empty($meta['range_to'])) ~ {{ $meta['range_to'] }} @endif
        {{-- Generated: {{ $meta['generated_at'] }} --}}
    </div>

    @forelse($groupsByDate as $ymd => $rows)
    <h2>{{ $ymd }}</h2>

    <table>
        <thead>
            <tr>
                <th>School</th>
                @if($mode === 'ot')
                <th>Original Teacher</th>
                @endif
                <th>Start</th>
                <th>End</th>
                <th class="col-classes">Classes</th>
                @if($mode === 'alp')
                <th>Leave Type</th>
                @endif
                @if($mode === 'ot')
                <th>Shift Type</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $e)
            <tr>
                <td>{{ $e->school_name }}</td>

                @if($mode === 'ot')
                <td>{{ optional($e->originalUser)->name }}</td>
                @endif

                <td>{{ $fmtTime($e->start_time) }}</td>
                <td>{{ $fmtTime($e->end_time) }}</td>
                <td class="col-classes">{{ $e->Lesson }}</td>

                @if($mode === 'alp')
                <td>{{ $e->Leave_type }}</td>
                @endif

                @if($mode === 'ot')
                <td>{{ $e->type_label }}</td>
                @endif
            </tr>
            @empty
            <tr>
                <td colspan="{{ $mode==='ot' ? 6 : 5 }}">No records.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @empty
    <p>No records.</p>
    @endforelse
</body>

</html>