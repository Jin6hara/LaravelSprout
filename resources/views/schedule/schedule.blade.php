@extends('layouts.app')
@php
use App\Support\TimeString;
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">All Schedules</h2>
    <span class="badge text-bg-primary">Admin View (Editable)</span>
</div>

{{-- ✅ 検索フォーム --}}
<form method="GET" class="card mb-2 p-2">
    <div class="row g-1 align-items-end">
        {{-- active_on --}}
        <div class="col-md-2">
            <label class="form-label small mb-1">Active On</label>
            <input type="date" name="active_on" class="form-control form-control-sm"
                value="{{ request('active_on') }}">
        </div>

        {{-- Active Until --}}
        <div class="col-md-2">
            <label class="form-label small mb-1">Active Until</label>
            <input type="date" name="active_until" class="form-control form-control-sm"
                value="{{ request('active_until') }}">
        </div>

        {{-- ユーザー選択 --}}
        <div class="col-md-3">
            <label class="form-label small mb-1">User</label>
            <select name="user_id" class="form-select form-select-sm">
                <option value="">— All Users —</option>
                @foreach($userOptions as $u)
                <option value="{{ $u->id }}" @selected(request('user_id')==$u->id)>
                    {{ $u->first_name }} {{ $u->family_name }} [{{ $u->employee_code }}]
                </option>
                @endforeach
            </select>
        </div>

        {{-- active --}}
        <div class="col-md-1">
            <label class="form-label small mb-1">Active</label>
            <select name="active" class="form-select form-select-sm">
                <option value="">—</option>
                <option value="1" @selected(request('active')==='1' )>Active</option>
                <option value="0" @selected(request('active')==='0' )>Inactive</option>
            </select>
        </div>

        {{-- label --}}
        <div class="col-md-3">
            <label class="form-label small mb-1">Label</label>
            <input type="text" name="label" class="form-control form-control-sm"
                value="{{ request('label') }}" placeholder="Weekly / Summer etc">
        </div>

        <div class="col-md-1 text-end">
            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Search</button>
        </div>
    </div>
</form>

<div class="row g-1">
    @forelse($schedules as $schedule)
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $schedule->user->first_name ?? '' }} {{ $schedule->user->family_name ?? '' }}</strong>
                    <span class="text-muted small ms-2">[ID: {{ $schedule->user->id }}]</span>
                </div>
                <span class="badge {{ $schedule->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                    {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('schedules.update', $schedule) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-1 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Label</label>
                            <input type="text" name="label" class="form-control form-control-sm"
                                value="{{ old('label', $schedule->label) }}">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small mb-1">Start</label>
                            <input type="date" name="effective_start"
                                class="form-control form-control-sm"
                                value="{{ TimeString::normalizeToYmd($schedule->effective_start) }}">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small mb-1">End</label>
                            <input type="date" name="effective_end"
                                class="form-control form-control-sm"
                                value="{{ TimeString::normalizeToYmd($schedule->effective_end) }}">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small mb-1">Active</label>
                            <select name="is_active" class="form-select form-select-sm">
                                <option value="1" @selected($schedule->is_active)>Active</option>
                                <option value="0" @selected(!$schedule->is_active)>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small mb-1">Total Minutes</label>
                            <input type="number" class="form-control form-control-sm" value="{{ $schedule->total_minutes }}" readonly>
                        </div>

                        <div class="col-md-1 text-end">
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12 text-muted small">No schedules found.</div>
    @endforelse
</div>
@endsection