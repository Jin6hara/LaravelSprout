@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Data</h2>

    <div class="list-group">
        <a href="{{ route('csv.index') }}" class="list-group-item list-group-item-action">
            CSV
        </a>
        <a href="{{ route('schools.search') }}" class="list-group-item list-group-item-action">
            School Search
        </a>
        <span class="list-group-item text-muted">
            Lesson
        </span>
        <a href="{{ route('data.calendar_pattern') }}" class="list-group-item list-group-item-action">
            Calendar Pattern
        </a>
        <span class="list-group-item text-muted">
            District &amp; Department
        </span>
    </div>
</div>
@endsection
