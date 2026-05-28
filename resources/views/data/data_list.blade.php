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
        <a href="{{ route('data.lessons.index') }}" class="list-group-item list-group-item-action">
            Lesson
        </a>
        <a href="{{ route('data.calendar_pattern') }}" class="list-group-item list-group-item-action">
            Calendar Pattern
        </a>
        @can('viewAny', \App\Models\District::class)
        <a href="{{ route('data.district_department') }}" class="list-group-item list-group-item-action">
            District &amp; Department
        </a>
        @endcan
        @role('super_admin')
        <a href="{{ route('data.role_manage') }}" class="list-group-item list-group-item-action">
            Role Management
        </a>
        @endrole
    </div>
</div>
@endsection
