@extends('layouts.app')

@section('title', 'Register Commuter Pass')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/expenses.css') }}?v={{ filemtime(public_path('css/expenses.css')) }}">
@endpush

@section('content')
@php
    $isEditingPass = $selectedPass !== null;
    $showPassActions = $canManagePasses ?? false;
    $newPassUrl = $isAdminMode
        ? route('commuter_passes.admin.create', ['user' => $target])
        : route('commuter_passes.create');
@endphp

<div class="page-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="mb-0">
            {{ $isEditingPass ? 'Edit Commuter Pass' : 'Register Commuter Pass' }}
            <small class="text-muted fs-6">
                for {{ $target->first_name }} {{ $target->family_name }} ({{ $target->employee_code }})
            </small>
        </h1>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm btn-route-fixed">
                Back
            </a>
            @if($isEditingPass)
                <a href="{{ $newPassUrl }}" class="btn btn-outline-primary btn-sm btn-route-fixed">
                    ＋ New Pass
                </a>
            @endif
        </div>
    </div>

    <form method="POST"
        action="{{ $isEditingPass
                    ? route('commuter_passes.update', $selectedPass)
                    : ($isAdminMode
                        ? route('commuter_passes.admin.store', $target)
                        : route('commuter_passes.store')) }}">
        @csrf
        @if($isEditingPass)
            @method('PUT')
        @endif

        <div class="header-box register-pass-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h6 class="mb-1 fw-bold">Commuter Pass Info</h6>
                    <div class="text-muted small">
                        {{ $isEditingPass ? 'Update the valid period, route, and optional fare amount.' : 'Register the valid period, route, and optional fare amount.' }}
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
                        value="{{ old('date_from', $selectedPass?->date_from?->format('Y-m-d')) }}"
                        class="form-control form-control-sm"
                        required>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="dateTo" class="form-label small mb-0">Valid To <span class="text-danger">*</span></label>
                    <input type="date"
                        id="dateTo"
                        name="date_to"
                        value="{{ old('date_to', $selectedPass?->date_to?->format('Y-m-d')) }}"
                        class="form-control form-control-sm"
                        required>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="stationFrom" class="form-label small mb-0">From Station <span class="text-danger">*</span></label>
                    <input type="text"
                        id="stationFrom"
                        name="station_from"
                        value="{{ old('station_from', $selectedPass?->station_from) }}"
                        class="form-control form-control-sm"
                        required>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="stationTo" class="form-label small mb-0">To Station <span class="text-danger">*</span></label>
                    <input type="text"
                        id="stationTo"
                        name="station_to"
                        value="{{ old('station_to', $selectedPass?->station_to) }}"
                        class="form-control form-control-sm"
                        required>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="cost" class="form-label small mb-0">Cost (JPY)</label>
                    <input type="number"
                        id="cost"
                        name="cost"
                        value="{{ old('cost', $selectedPass?->cost) }}"
                        class="form-control form-control-sm"
                        min="0" step="1">
                </div>

                <div class="col-sm-6 col-lg-9">
                    <label for="note" class="form-label small mb-0">Note</label>
                    <textarea name="note"
                        id="note"
                        rows="1"
                        class="form-control form-control-sm">{{ old('note', $selectedPass?->note) }}</textarea>
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-sm btn-route-fixed">
                    {{ $isEditingPass ? 'Update' : 'Register' }}
                </button>
            </div>
        </div>
    </form>

    <div class="header-box register-pass-history mt-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h6 class="mb-0 fw-bold">Commuter Pass History</h6>
            <span class="text-muted small">{{ $passHistory->total() }} item(s)</span>
        </div>

        @if($passHistory->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 register-pass-history-table">
                    <thead>
                        <tr>
                            <th scope="col">Valid Period</th>
                            <th scope="col">Route</th>
                            <th scope="col" class="text-end">Cost</th>
                            <th scope="col">Note</th>
                            @if($showPassActions)
                            <th scope="col" class="text-end">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($passHistory as $pass)
                            @php
                                $passUrl = $isAdminMode
                                    ? route('commuter_passes.admin.create', ['user' => $target, 'pass' => $pass->id])
                                    : route('commuter_passes.create', ['pass' => $pass->id]);
                                $isCurrentPass = $selectedPass?->id === $pass->id;
                            @endphp
                            <tr @class(['table-primary' => $isCurrentPass])>
                                <td class="text-nowrap">
                                    {{ $pass->date_from?->format('Y-m-d') }} - {{ $pass->date_to?->format('Y-m-d') }}
                                </td>
                                <td>
                                    {{ $pass->station_from }} <span class="text-muted">→</span> {{ $pass->station_to }}
                                </td>
                                <td class="text-end text-nowrap">¥{{ number_format((int) $pass->cost) }}</td>
                                <td class="register-pass-history-note">
                                    {{ $pass->note ?: '-' }}
                                </td>
                                @if($showPassActions)
                                <td class="text-end text-nowrap">
                                    @if($isCurrentPass)
                                        <button type="button" class="btn btn-primary btn-sm" disabled>Editing</button>
                                    @else
                                        <a href="{{ $passUrl }}" class="btn btn-outline-secondary btn-sm">Load</a>
                                    @endif
                                    <form method="POST"
                                        action="{{ route('commuter_passes.destroy', $pass) }}"
                                        class="d-inline"
                                        onsubmit="return confirm('Delete this commuter pass?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-2 register-pass-history-pagination">
                {{ $passHistory->links() }}
            </div>
        @else
            <div class="text-muted small border rounded p-2 bg-white">No commuter pass history to display.</div>
        @endif
    </div>
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

    .register-pass-history-table {
        font-size: .875rem;
    }

    .register-pass-history-table thead th {
        color: #6b7280;
        font-weight: 600;
        border-bottom-color: #cfd8ea;
    }

    .register-pass-history-note {
        min-width: 180px;
        max-width: 420px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .register-pass-history-pagination .pagination {
        margin-bottom: 0;
        justify-content: flex-end;
    }
</style>
@endpush
