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

@if(session('success'))  <div class="alert alert-success py-2 mb-2">{{ session('success') }}</div> @endif
@if(session('error'))    <div class="alert alert-danger  py-2 mb-2">{{ session('error') }}</div>   @endif
@if ($errors->any())
  <div class="alert alert-danger py-2 mb-2">
    <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
  </div>
@endif

@php
  $kindLabels = $kindLabels ?? [
    'absence'         => 'Unpaid Leave',
    'absense_to_paid' => 'ALP',
    'other'           => 'Others',
  ];
  $handleTypeOptions = $handleTypeOptions ?? [
    'self_cover' => 'Self Cover',
    'makeup'     => 'Make-up Lesson',
    'refund'     => 'Refund',
    'other'      => 'Other',
  ];
@endphp

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 absence-table">
        {{-- ★ 列幅を安定させる --}}
        <colgroup>
          <col style="width:12rem">
          <col style="width:10rem">
          <col style="width:auto">
          <col style="width:16rem">
          <col style="width:8rem">
          <col style="width:9rem">
        </colgroup>

        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Kind</th>
            <th>Reason</th>
            <th>Handle Type</th>
            <th>Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>

        <tbody>
        @forelse($leaves as $leave)
          @php
            $kindLabel = $leave->kind === 'other'
              ? ($leave->special_type ?: 'Others')
              : ($kindLabels[$leave->kind] ?? ucfirst($leave->kind));

            $needsReport = ($leave->kind === 'absence') && is_null($leave->reason) && is_null($leave->handle_type);
            $statusText  = ($leave->reason && $leave->handle_type) ? 'Submitted' : ($needsReport ? 'Required' : '—');

            $dateMain = optional($leave->start_date)->toDateString();
            $dateSub  = (!empty($leave->end_date) && $leave->end_date?->ne($leave->start_date)) ? $leave->end_date->toDateString() : null;

            $formId = 'report-form-'.$leave->id;
          @endphp

          <tr class="{{ $needsReport ? 'row-required' : '' }}">
            {{-- Date --}}
            <td>
              <div class="d-flex flex-column">
                <span class="fw-semibold">{{ $dateMain }}</span>
                @if($dateSub)<span class="small text-muted">〜 {{ $dateSub }}</span>@endif
              </div>
            </td>

            {{-- Kind --}}
            <td>
              <span class="badge rounded-pill
                @switch($kindLabel)
                  @case('Unpaid Leave') text-bg-primary text-white @break
                  @case('ALP')          text-bg-info    text-white @break
                  @default              text-bg-info    text-white
                @endswitch
              ">{{ $kindLabel }}</span>
            </td>

            {{-- Reason --}}
            <td class="col-reason">
              @if($needsReport)
                <textarea name="reason"
                          form="{{ $formId }}"
                          class="form-control form-control-sm"
                          rows="2"
                          placeholder="Enter reason (required)"
                          required></textarea>
              @else
                <textarea class="form-control form-control-sm bg-body-tertiary"
                          rows="2"
                          readonly>{{ $leave->reason }}</textarea>
              @endif
            </td>

            {{-- Handle Type --}}
            <td class="col-handle">
              @if($needsReport)
                <select name="handle_type"
                        form="{{ $formId }}"
                        class="form-select form-select-sm"
                        required>
                  <option value="">— Select —</option>
                  @foreach($handleTypeOptions as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                  @endforeach
                </select>
              @else
                @php
                  $handleLabel = $leave->handle_type ? ($handleTypeOptions[$leave->handle_type] ?? $leave->handle_type) : '';
                @endphp
                <input type="text"
                      class="form-control form-control-sm bg-body-tertiary"
                      value="{{ $handleLabel }}"
                      readonly>
              @endif
            </td>

            {{-- Status --}}
            <td>
              @if($statusText === 'Submitted')
                <span class="badge rounded-pill text-bg-success">Submitted</span>
              @elseif($statusText === 'Required')
                <span class="badge rounded-pill text-bg-warning text-dark">Required</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            {{-- Action --}}
            <td class="text-end">
              @if($needsReport)
                <form id="{{ $formId }}" method="POST" action="{{ route('report.update', $leave) }}" class="d-inline-block">
                  @csrf @method('PUT')
                  <button type="submit" class="btn btn-sm btn-primary">Submit</button>
                </form>
              @else
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Submitted</button>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No records.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  /* 必要行のハイライト（疑似要素は使わない） */
  .absence-table tr.row-required { background: linear-gradient(90deg, rgba(252,211,77,.12), transparent 45%); }
  .absence-table tr.row-required td:first-child { border-left: 4px solid #f59e0b; }

  /* 読取専用の背景 */
  .form-control[readonly], .bg-body-tertiary { background-color: var(--bs-tertiary-bg) !important; }

  /* スマホ：theadを非表示にしてカード風に */
  @media (max-width: 768px) {
    .absence-table thead { display:none; }
    .absence-table colgroup { display:none; }
    .absence-table tbody tr { display:grid; grid-template-columns:1fr; gap:.5rem .75rem; padding:.75rem; border-top:1px solid var(--bs-border-color); }
    .absence-table tbody td { display:grid; grid-template-columns:9rem 1fr; align-items:center; gap:.5rem; padding:.25rem 0; border:0 !important; }
    .absence-table tbody td::before { content: attr(data-label); font-size:.8rem; color:var(--bs-secondary-color); }
  }
/* 高さ・フォント・角丸を統一 */
.absence-table .btn-sm,
.absence-table .badge.rounded-pill {
  font-size: 0.8rem;
  font-weight: 400; /* ← 太字を解除 */
  padding: 0.35rem 0.75rem;
  border-radius: 1rem !important;
  line-height: 1.2;
}

/* ボタンの色統一 */
.absence-table .btn-primary {
  background-color: #2563eb; /* Indigo-600 */
  border-color: #2563eb;
}
.absence-table .btn-primary:hover {
  background-color: #1e40af;
  border-color: #1e40af;
}

/* アウトラインボタン（Submitted用） */
.absence-table .btn-outline-secondary {
  color: #6b7280;
  border-color: #cbd5e1;
  background-color: #f9fafb;
}
.absence-table .btn-outline-secondary:disabled {
  opacity: 1;
  color: #9ca3af;
}

/* バッジ配色調整（Bootstrapベース） */
.badge.text-bg-success {
  background-color: #22c55e !important;
  font-weight: 400 !important;
}
.badge.text-bg-warning {
  background-color: #facc15 !important;
  color: #1e293b !important;
  font-weight: 400 !important;
}
.badge.text-bg-primary {
  background-color: #3b82f6 !important;
  font-weight: 400 !important;
}
.badge.text-bg-info {
  background-color: #38bdf8 !important;
  font-weight: 400 !important;
}
/* Reason と Handle Type の列幅をやや短く（Date列を確保） */
.absence-table .col-reason,
.absence-table .col-handle {
  width: 30%;
  min-width: 5rem;
}

/* テキストエリアの高さと見た目を自然に */
.absence-table textarea.form-control-sm {
  resize: vertical;
  line-height: 1.3;
  padding: 0.35rem 0.5rem;
  height: 2.4rem; /* 少し短め固定（2行相当） */
  min-height: 2.4rem;
}
</style>

@endpush
