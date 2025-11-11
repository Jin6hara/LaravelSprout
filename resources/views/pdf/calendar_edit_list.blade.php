<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 15mm;
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

        .small {
            font-size: 9pt;
            color: #555;
        }
    </style>
</head>

<body>
    <h1>Events (Filtered List)</h1>

    <div class="meta small">
        期間:
        @if($meta['range_from']) {{ $meta['range_from'] }} @endif
        @if($meta['range_to']) 〜 {{ $meta['range_to'] }} @endif
        ／ 生成: {{ $meta['generated_at'] }}
    </div>

    @php
    $ex = collect($excludeCols);
    $hide = fn($k) => $ex->contains($k);
    @endphp

    <table>
        <thead>
            <tr>
                @unless($hide('event_date')) <th>Date</th> @endunless
                @unless($hide('school_name')) <th>School</th> @endunless
                @unless($hide('start_time')) <th>Start</th> @endunless
                @unless($hide('end_time')) <th>End</th> @endunless
                @unless($hide('title')) <th>Title</th> @endunless
                @unless($hide('lesson')) <th>Lesson</th> @endunless
                @unless($hide('status')) <th>Status</th> @endunless
                @unless($hide('type')) <th>Type</th> @endunless
                @unless($hide('assigned_user')) <th>Assigned</th> @endunless
            </tr>
        </thead>
        <tbody>
            @forelse($events as $e)
            <tr>
                @unless($hide('event_date')) <td>{{ $e->event_date }}</td> @endunless
                @unless($hide('school_name')) <td>{{ $e->school_name }}</td> @endunless
                @unless($hide('start_time')) <td>{{ optional($e->start_time)->format('H:i') ?? '' }}</td> @endunless
                @unless($hide('end_time')) <td>{{ optional($e->end_time)->format('H:i') ?? '' }}</td> @endunless
                @unless($hide('title')) <td>{{ $e->title }}</td> @endunless
                @unless($hide('lesson')) <td>{{ $e->Lesson }}</td> @endunless
                @unless($hide('status')) <td>{{ $e->status }}</td> @endunless
                @unless($hide('type')) <td>{{ $e->type }}</td> @endunless
                @unless($hide('assigned_user'))
                <td>
                    @if($e->assignedUser)
                    {{ $e->assignedUser->name }} [{{ $e->assignedUser->employee_code }}]
                    @endif
                </td>
                @endunless
            </tr>
            @empty
            <tr>
                <td colspan="9">No records.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>