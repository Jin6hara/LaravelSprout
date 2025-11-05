@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Overtime (In Process)</h2>

    @if($events->isEmpty())
    <div class="alert alert-secondary">No overtime records found.</div>
    @else
    <table class="table table-sm table-striped align-middle">
        <thead>
            <tr>
                <th>Date</th>
                <th>Start</th>
                <th>End</th>
                <th>Title</th>
                <th>School</th>
                <th>Duration</th>
                <th>Assigned User</th>
            </tr>
        </thead>
        <tbody>
            @foreach($events as $e)
            <tr>
                {{-- event_date は日付型なので、時間を含まず表示 --}}
                <td>{{ \Carbon\Carbon::parse($e->event_date)->format('Y-m-d') }}</td>

                {{-- 時間は HH:MM 形式 --}}
                <td>
                    @if($e->start_time)
                    {{ \Carbon\Carbon::parse($e->start_time)->format('H:i') }}
                    @else
                    —
                    @endif
                </td>
                <td>
                    @if($e->end_time)
                    {{ \Carbon\Carbon::parse($e->end_time)->format('H:i') }}
                    @else
                    —
                    @endif
                </td>

                <td>{{ $e->title }}</td>
                <td>{{ $e->school_name }}</td>
                <td>{{ $e->total_duration }}</td>
                <td>{{ optional($e->assignedUser)->first_name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection