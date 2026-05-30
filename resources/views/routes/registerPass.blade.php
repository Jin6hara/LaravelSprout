@extends('layouts.app')

@section('title', 'Register Commuter Pass')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/expenses.css') }}?v={{ filemtime(public_path('css/expenses.css')) }}">
@endpush

@section('content')
<div class="page-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="mb-0">
            Register Commuter Pass
            <small class="text-muted fs-6">
                for {{ $target->first_name }} {{ $target->family_name }} ({{ $target->employee_code }})
            </small>
        </h1>

        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm btn-route-fixed">
            Back
        </a>
    </div>

    @if(session('toast'))
    <div class="alert alert-success py-2 px-3">
        {{ session('toast') }}
    </div>
    @endif

    @if ($errors->any())
    <div class="alert alert-danger py-2 px-3">
        <ul class="mb-0">
            @foreach ($errors->all() as $msg)
            <li>{{ $msg }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST"
        action="{{ $isAdminMode
                    ? route('commuter_passes.admin.store', $target)
                    : route('commuter_passes.store') }}">
        @csrf

        <div class="header-box register-pass-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h6 class="mb-1 fw-bold">Commuter Pass Info</h6>
                    <div class="text-muted small">
                        Register the valid period, route, and optional fare amount.
                    </div>
                </div>
                <div class="text-muted small text-end">
                    <span class="text-danger">*</span> Required
                </div>
            </div>

            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="dateFrom" class="form-label small mb-0">Valid From <span class="text-danger">*</span></label>
                    <input type="date"
                        id="dateFrom"
                        name="date_from"
                        value="{{ old('date_from') }}"
                        class="form-control form-control-sm"
                        required>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="dateTo" class="form-label small mb-0">Valid To <span class="text-danger">*</span></label>
                    <input type="date"
                        id="dateTo"
                        name="date_to"
                        value="{{ old('date_to') }}"
                        class="form-control form-control-sm"
                        required>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="stationFrom" class="form-label small mb-0">From Station <span class="text-danger">*</span></label>
                    <input type="text"
                        id="stationFrom"
                        name="station_from"
                        value="{{ old('station_from') }}"
                        class="form-control form-control-sm"
                        required>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="stationTo" class="form-label small mb-0">To Station <span class="text-danger">*</span></label>
                    <input type="text"
                        id="stationTo"
                        name="station_to"
                        value="{{ old('station_to') }}"
                        class="form-control form-control-sm"
                        required>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="cost" class="form-label small mb-0">Cost (JPY)</label>
                    <input type="number"
                        id="cost"
                        name="cost"
                        value="{{ old('cost') }}"
                        class="form-control form-control-sm"
                        min="0" step="1">
                </div>

                <div class="col-12">
                    <label for="note" class="form-label small mb-0">Note</label>
                    <textarea name="note"
                        id="note"
                        rows="2"
                        class="form-control form-control-sm">{{ old('note') }}</textarea>
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-sm btn-route-fixed">
                    Register
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .btn-route-fixed {
        width: 150px;
        text-align: center;
        white-space: nowrap;
    }

    .register-pass-panel .form-control-sm {
        min-height: 32px;
    }
</style>
@endpush
