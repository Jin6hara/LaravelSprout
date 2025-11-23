{{-- resources/views/routes/declarationReport.blade.php --}}
@extends('layouts.app')

@section('title', 'Route Declarations Report（管理者）')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
{{-- 自作CSS（必要なら） --}}
<link rel="stylesheet"
    href="{{ asset('css/routeDeclarationReport.css') }}?v={{ @filemtime(public_path('css/routeDeclarationReport.css')) }}">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js"></script>
{{-- 自作JS --}}
<script src="{{ asset('js/routeDeclarationReport.js') }}?v={{ @filemtime(public_path('js/routeDeclarationReport.js')) }}"></script>
@endpush

@section('content')
<div class="page-wrap">
    <h1>Route Declarations Report（Active on {{ $activeOn }}）</h1>

    {{-- フィルタフォーム --}}
    <form method="GET" class="card p-3 mb-3">
        <div class="row g-3 align-items-end">

            {{-- active_on --}}
            <div class="col-md-2">
                <label class="form-label">Active on</label>
                <input type="date"
                    name="active_on"
                    value="{{ $activeOn }}"
                    class="form-control">
            </div>

            {{-- Target Users（ドラッグ／タブ風ボタン） --}}
            <div class="col-md-5">
                <label class="form-label d-block mb-1">Teacher</label>
                <div class="btn-group" role="group" aria-label="Target users">
                    {{-- Schedule --}}
                    <input type="radio"
                        class="btn-check"
                        name="mode"
                        id="modeSchedule"
                        value="schedule"
                        autocomplete="off"
                        {{ $mode === 'schedule' ? 'checked' : '' }}>
                    <label class="btn btn-outline-secondary btn-target-mode" for="modeSchedule">
                        Valid Schedule
                    </label>

                    {{-- Employment --}}
                    <input type="radio"
                        class="btn-check"
                        name="mode"
                        id="modeEmployment"
                        value="employment"
                        autocomplete="off"
                        {{ $mode === 'employment' ? 'checked' : '' }}>
                    <label class="btn btn-outline-secondary btn-target-mode" for="modeEmployment">
                        Valid Contract
                    </label>

                    {{-- Specific user --}}
                    <input type="radio"
                        class="btn-check"
                        name="mode"
                        id="modeUser"
                        value="user"
                        autocomplete="off"
                        {{ $mode === 'user' ? 'checked' : '' }}>
                    <label class="btn btn-outline-secondary btn-target-mode" for="modeUser">
                        Search
                    </label>
                </div>
            </div>
            {{-- Specific user 用セレクト --}}
            <div class="col-md-3 ms-md-auto text-md-end" id="specificUserBlock">
                <select name="user_id" class="form-select d-inline-block" style="max-width: 100%;">
                    <option value="">-- Select User --</option>
                    @foreach($allUsers as $u)
                    <option value="{{ $u->id }}"
                        {{ (int)$selectedUserId === $u->id ? 'selected' : '' }}>
                        {{ trim($u->first_name . ' ' . $u->family_name) }} [{{ $u->employee_code }}]
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">
                    Filter
                </button>
            </div>
        </div>
    </form>

    {{-- サマリ --}}
    <div class="header-box mb-4">
        <div class="meta w-100" style="gap:24px">
            <div>Total Target Users: <strong>{{ number_format($summary['total_users']) }}</strong></div>
            <div>Submitted: <strong>{{ number_format($summary['submitted']) }}</strong></div>
            <div>Not Submitted: <strong>{{ number_format($summary['not_submitted']) }}</strong></div>
            <div>Shown Rows: <strong>{{ number_format($summary['shown_rows']) }}</strong></div>
        </div>
    </div>

    {{-- テーブル --}}
    <div id="sheet"></div>

    {{-- JS に渡す JSON データ --}}
    <script id="routeDeclarationData"
        type="application/json"
        data-active-on="{{ $activeOn }}">
        @json($rows)
    </script>
</div>
@endsection