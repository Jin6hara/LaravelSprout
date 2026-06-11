{{-- 全スタッフの欠勤・休暇レポートを管理者向けに一覧表示するビュー --}}
@extends('layouts.app')

@section('title', 'All Absence Reports')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">All Absence Reports</h2>
    </div>
    <span class="badge text-bg-primary">Admin View</span>
</div>

@if ($errors->any())
<div class="alert alert-danger py-2 mb-2">
    <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

{{-- フィルタ --}}
<form method="GET" class="card card-body mb-3 p-2">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                @php $st = $filters['status'] ?? 'all'; @endphp
                <option value="all" @selected($st==='all' )>All</option>
                <option value="required" @selected($st==='required' )>Required</option>
                <option value="submitted" @selected($st==='submitted' )>Submitted</option>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Kind</label>
            <select name="kind" class="form-select form-select-sm">
                @php $kd = $filters['kind'] ?? 'absence'; @endphp
                <option value="absence" @selected($kd==='absence' )>Unpaid Leave</option>
                <option value="absence_to_paid" @selected($kd==='absence_to_paid' )>ALP</option>
                <option value="other" @selected($kd==='other' )>Others</option>
                <option value="all" @selected($kd==='all' )>All</option>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
        </div>

        <div class="col-12 col-md-3">
            <label class="form-label small mb-1">User</label>
            <select name="user_id" class="form-select form-select-sm">
                <option value="">— All Users —</option>
                @php $uid = $filters['userId'] ?? null; @endphp
                @foreach($userOptions as $u)
                @php
                $uname = trim(($u->family_name ?? '').' '.($u->first_name ?? '')) ?: ('User #'.$u->id);
                $ucode = $u->employee_code ? " [{$u->employee_code}]" : '';
                @endphp
                <option value="{{ $u->id }}" @selected($uid==$u->id)>{{ $uname }}{{ $ucode }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-12 col-md-1 d-grid">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </div>
    </div>
</form>

{{-- 一覧（カード行） --}}
@if($leaves->count())
@foreach($leaves as $row)
@php($leave = $row['leave'])
<div class="leave-row border rounded-2 p-2 mb-1 {{ $row['needsReport'] ? 'bg-required' : '' }}">
    <div class="row g-1 align-items-end">
        {{-- User --}}
        <div class="col-6 col-sm-6 col-md-6 col-lg-2 mb-1">
            <div class="small text-muted mb-1">User</div>
            <div class="fw-semibold">{{ $row['userName'] }}
                @if(!empty($row['employeeCode']))
                <span class="text-muted">[{{ $row['employeeCode'] }}]</span>
                @endif
            </div>
        </div>

        {{-- Date --}}
        <div class="col-6 col-sm-6 col-md-6 col-lg-2 mb-1">
            <div class="small text-muted mb-1">Date</div>
            <div class="fw-semibold">{{ $row['dateMain'] }}</div>
            @if($row['dateSub'])
            <div class="small text-muted">〜 {{ $row['dateSub'] }}</div>
            @endif
        </div>

        {{-- Kind --}}
        <div class="col-6 col-sm-6 col-md-6 col-lg-2">
            <div class="small text-muted mb-1">Kind</div>
            <span class="badge rounded-pill
            @switch($row['kindLabel'])
              @case('Unpaid Leave') text-bg-primary text-white @break
              @case('ALP')          text-bg-info    text-white @break
              @default              text-bg-info    text-white
            @endswitch
          ">{{ $row['kindLabel'] }}</span>
        </div>

        {{-- Status --}}
        <div class="col-6 col-sm-6 col-md-6 col-lg-1">
            <div class="small text-muted mb-1">Status</div>
            @if($row['statusText'] === 'Submitted')
            <span class="badge rounded-pill text-bg-success">Submitted</span>
            @elseif($row['statusText'] === 'Required')
            <span class="badge rounded-pill text-bg-warning text-dark">Required</span>
            @else
            <span class="text-muted">—</span>
            @endif
        </div>

        {{-- Reason (read-only in ALL view) --}}
        <div class="col-12 col-sm-12 col-md-6 col-lg-2">
            <div class="small text-muted mb-1">Reason</div>
            <textarea class="form-control form-control-sm bg-body-tertiary" rows="1" readonly>{{ $leave->reason }}</textarea>
        </div>

        {{-- Handle Type --}}
        <div class="col-12 col-sm-12 col-md-6 col-lg-2">
            <div class="small text-muted mb-1">Handle Type</div>
            <input type="text" class="form-control form-control-sm bg-body-tertiary"
                value="{{ $row['handleLabel'] }}" readonly>
        </div>

        {{-- Open user page --}}
        <div class="col-6 col-sm-6 col-md-3 col-lg-1 ms-auto d-grid">
            <a href="{{ route('absence.edit', $leave->user->employee_code) }}" class="btn btn-sm btn-outline-secondary">
                Details
            </a>
        </div>
    </div>
</div>
@endforeach

<div class="mt-3">
    {{ $leaves->links() }}
</div>
@else
<div class="text-center text-muted py-5">No records.</div>
@endif
@endsection

@push('styles')
<style>
    .bg-required {
        background: linear-gradient(90deg, rgba(252, 211, 77, .12), transparent 55%);
        border-left: 4px solid #f59e0b !important;
    }

    .form-control[readonly],
    .bg-body-tertiary {
        background-color: var(--bs-tertiary-bg) !important;
    }

    .badge.rounded-pill {
        font-size: .8rem;
        font-weight: 400 !important;
        padding: .48rem .65rem;
        border-radius: 1rem !important;
        line-height: 1.2;
    }

    .badge.text-bg-success {
        background-color: #22c55e !important;
    }

    .badge.text-bg-warning {
        background-color: #facc15 !important;
        color: #1e293b !important;
    }

    .badge.text-bg-primary {
        background-color: #3b82f6 !important;
    }

    .badge.text-bg-info {
        background-color: #38bdf8 !important;
    }
</style>
@endpush