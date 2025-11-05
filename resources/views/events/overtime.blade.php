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
                <th>Title</th>
                <th>School</th>
                <th>Start</th>
                <th>End</th>
                <th>Duration</th>
                <th>Assigned User</th>
            </tr>
        </thead>
        <tbody>
            @foreach($events as $e)
            <tr>
                <td>{{ $e->event_date }}</td>
                <td>{{ $e->title }}</td>
                <td>{{ $e->school_name }}</td>
                <td>{{ $e->start_time }}</td>
                <td>{{ $e->end_time }}</td>
                <td>{{ $e->total_duration }}</td>
                <td>{{ optional($e->assignedUser)->first_name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection