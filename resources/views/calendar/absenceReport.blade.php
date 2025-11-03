{{-- resources/views/calendar/absenceReport.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="mb-0">Absence Report</h2>
    <div class="text-muted small mt-1">
      User: <span class="fw-semibold">{{ $user->name ?? trim(($user->family_name ?? '').' '.($user->first_name ?? '')) }}</span>
    </div>
  </div>
  @role('admin|super_admin')
  <span class="badge text-bg-primary">Admin View</span>
  @else
  <span class="badge text-bg-secondary">My Absences</span>
  @endrole
</div>

<div class="card">
  <div class="card-body p-2">

    @forelse($rows as $row)
    @php($leave = $row['leave'])
    <div class="leave-row border rounded-3 p-2 mb-2 {{ $row['needsReport'] ? 'bg-required' : '' }}">
      <div class="row g-2 align-items-end">

        {{-- Date --}}
        <div class="col-6 col-sm-6 col-md-6 col-lg-1 mb-1">
          <label class="form-label small text-muted mb-1">Date</label>
          <div class="fw-semibold">{{ $row['dateMain'] }}</div>
          @if($row['dateSub'])
          <div class="small text-muted">〜 {{ $row['dateSub'] }}</div>
          @endif
        </div>

        {{-- Kind --}}
        <div class="col-6 col-sm-6 col-md-6 col-lg-2">
          <label class="form-label small text-muted mb-1">Kind</label>
          <div>
            <span class="badge rounded-pill
            @switch($row['kindLabel'])
              @case('Unpaid Leave') text-bg-primary text-white @break
              @case('ALP')          text-bg-info    text-white @break
              @default              text-bg-info    text-white
            @endswitch
          ">{{ $row['kindLabel'] }}</span>
          </div>
        </div>

        {{-- Reason --}}
        <div class="col-12 col-sm-12 col-md-6 col-lg-3">
          <label class="form-label small text-muted mb-1">Reason</label>
          @if($row['needsReport'])
          <textarea
            name="reason"
            form="{{ $row['formId'] }}"
            class="form-control form-control-sm"
            rows="1"
            placeholder="Enter reason (required)"
            required></textarea>
          @else
          <textarea
            class="form-control form-control-sm bg-body-tertiary"
            rows="1"
            readonly>{{ $leave->reason }}</textarea>
          @endif
        </div>

        {{-- Handle Type --}}
        <div class="col-12 col-sm-12 col-md-6 col-lg-4">
          <label class="form-label small text-muted mb-1">Handle Type</label>
          @if($row['needsReport'])
          <select
            name="handle_type"
            form="{{ $row['formId'] }}"
            class="form-select form-select-sm"
            required>
            <option value="">— Select —</option>
            @foreach($handleTypeOptions as $val => $label)
            <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
          </select>
          @else
          <input
            type="text"
            class="form-control form-control-sm bg-body-tertiary"
            value="{{ $row['handleLabel'] }}"
            readonly>
          @endif
        </div>

        {{-- Status --}}
        <div class="col-6 col-sm-6 col-md-6 col-lg-1">
          <label class="form-label small text-muted mb-1">Status</label>
          <div>
            @if($row['statusText'] === 'Submitted')
            <span class="badge rounded-pill text-bg-success">Submitted</span>
            @elseif($row['statusText'] === 'Required')
            <span class="badge rounded-pill text-bg-warning text-dark">Required</span>
            @else
            <span class="text-muted">—</span>
            @endif
          </div>
        </div>

        {{-- Action --}}
        <div class="col-6 col-sm-6 col-md-6 col-lg-1 text-end">
          <label class="form-label small text-muted mb-1 d-none d-md-block">&nbsp;</label>
          @if($row['needsReport'])
          <form id="{{ $row['formId'] }}" method="POST" action="{{ route('report.update', $leave) }}" class="d-inline-block">
            @csrf @method('PUT')
            <button type="submit" class="btn btn-sm btn-primary w-100">Submit</button>
          </form>
          @else
          <button type="button" class="btn btn-sm btn-outline-secondary w-100" disabled>Submitted</button>
          @endif
        </div>

      </div>
    </div>
    @empty
    <div class="text-center text-muted py-4">No records.</div>
    @endforelse

  </div>
</div>
@endsection

@push('styles')
<style>
  /* 必要行のハイライト（カード版） */
  .bg-required {
    background: linear-gradient(90deg, rgba(252, 211, 77, .12), transparent 55%);
    border-left: 4px solid #f59e0b !important;
  }

  /* 読取専用の背景 */
  .form-control[readonly],
  .bg-body-tertiary {
    background-color: var(--bs-tertiary-bg) !important;
  }

  /* ボタン・バッジのトーン統一 */
  .badge.rounded-pill {
    font-size: 0.8rem;
    font-weight: 400 !important;
    padding: .48rem .65rem;
    border-radius: 1rem !important;
    line-height: 1.2;
  }

  .btn-primary {
    background-color: #2563eb;
    border-color: #2563eb;
  }

  .btn-primary:hover {
    background-color: #1e40af;
    border-color: #1e40af;
  }

  .btn-outline-secondary:disabled {
    opacity: 1;
    color: #9ca3af;
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