{{-- 全スタッフの欠勤・休暇レポートを管理者向けに一覧表示するビュー --}}
@extends('layouts.app')

@section('title', 'All Absence Reports')

@section('content')
<div class="ara-page">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0 fw-semibold">All Absence Reports</h2>
    <span class="ara-badge ara-badge--admin">Admin View</span>
  </div>

  @if ($errors->any())
  <div class="alert alert-danger py-2 mb-3">
    <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
  </div>
  @endif

  {{-- フィルタ --}}
  <form method="GET" class="ara-filter mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="ara-filter-label">Status</label>
        <select name="status" class="form-select form-select-sm">
          @php $st = $filters['status'] ?? 'all'; @endphp
          <option value="all"       @selected($st==='all'      )>All</option>
          <option value="required"  @selected($st==='required' )>Required</option>
          <option value="submitted" @selected($st==='submitted')>Submitted</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="ara-filter-label">Kind</label>
        <select name="kind" class="form-select form-select-sm">
          @php $kd = $filters['kind'] ?? 'absence'; @endphp
          <option value="absence"         @selected($kd==='absence'        )>Unpaid Leave</option>
          <option value="absence_to_paid" @selected($kd==='absence_to_paid')>ALP</option>
          <option value="other"           @selected($kd==='other'          )>Others</option>
          <option value="all"             @selected($kd==='all'            )>All</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="ara-filter-label">From</label>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
      </div>
      <div class="col-6 col-md-2">
        <label class="ara-filter-label">To</label>
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
      </div>
      <div class="col-12 col-md-3">
        <label class="ara-filter-label">User</label>
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
        <button type="submit" class="ara-filter-btn">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
      </div>
    </div>
  </form>

  {{-- 列ヘッダー（デスクトップのみ） --}}
  <div class="ara-header d-none d-lg-flex">
    <div class="ara-col ara-col--user">User</div>
    <div class="ara-col ara-col--date">Date</div>
    <div class="ara-col ara-col--kind">Kind</div>
    <div class="ara-col ara-col--status">Status</div>
    <div class="ara-col ara-col--reason">Reason</div>
    <div class="ara-col ara-col--handle">Handle Type</div>
    <div class="ara-col ara-col--action"></div>
  </div>

  {{-- 一覧 --}}
  @if($leaves->count())
  <div class="ara-list">
    @foreach($leaves as $row)
    @php($leave = $row['leave'])

    <div class="ara-card {{ $row['needsReport'] ? 'ara-card--required' : '' }}">

      {{-- User --}}
      <div class="ara-col ara-col--user">
        <span class="ara-mobile-label">User</span>
        <span class="fw-semibold">{{ $row['userName'] }}</span>
        @if(!empty($row['employeeCode']))
        <span class="ara-code">[{{ $row['employeeCode'] }}]</span>
        @endif
      </div>

      {{-- Date --}}
      <div class="ara-col ara-col--date">
        <span class="ara-mobile-label">Date</span>
        <span class="fw-semibold">{{ $row['dateMain'] }}</span>
        @if($row['dateSub'])
        <span class="text-muted" style="font-size:0.72rem;">〜{{ $row['dateSub'] }}</span>
        @endif
      </div>

      {{-- Kind --}}
      <div class="ara-col ara-col--kind">
        <span class="ara-mobile-label">Kind</span>
        <span class="ara-kind-badge ara-kind-badge--{{ Str::slug($row['kindLabel']) }}">{{ $row['kindLabel'] }}</span>
      </div>

      {{-- Status --}}
      <div class="ara-col ara-col--status">
        <span class="ara-mobile-label">Status</span>
        @if($row['statusText'] === 'Submitted')
        <span class="ara-status ara-status--done"><i class="bi bi-check-circle-fill me-1"></i>Submitted</span>
        @elseif($row['statusText'] === 'Required')
        <span class="ara-status ara-status--pending"><i class="bi bi-exclamation-circle-fill me-1"></i>Required</span>
        @else
        <span class="text-muted">—</span>
        @endif
      </div>

      {{-- Reason --}}
      <div class="ara-col ara-col--reason">
        <span class="ara-mobile-label">Reason</span>
        <textarea class="form-control form-control-sm" rows="1" readonly>{{ $leave->reason }}</textarea>
      </div>

      {{-- Handle Type --}}
      <div class="ara-col ara-col--handle">
        <span class="ara-mobile-label">Handle Type</span>
        <input type="text" class="form-control form-control-sm" value="{{ $row['handleLabel'] }}" readonly>
      </div>

      {{-- Details --}}
      <div class="ara-col ara-col--action">
        <a href="{{ route('absence.edit', $leave->user->employee_code) }}" class="ara-details-btn">
          <i class="bi bi-arrow-right me-1"></i>Details
        </a>
      </div>

    </div>
    @endforeach
  </div>

  <div class="mt-3">{{ $leaves->links() }}</div>

  @else
  <div class="ara-empty">
    <i class="bi bi-inbox fs-2 d-block mb-2"></i>No records.
  </div>
  @endif

</div>
@endsection

@push('styles')
<style>
  /* ===== すべて .ara-page 以下にスコープ ===== */

  .ara-page .ara-badge {
    font-size: 0.75rem; font-weight: 500;
    padding: 0.3rem 0.75rem; border-radius: 9999px;
  }
  .ara-page .ara-badge--admin { background: #dbeafe; color: #1d4ed8; }

  /* フィルタ */
  .ara-page .ara-filter {
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 0.6rem; padding: 0.75rem 1rem;
  }
  .ara-page .ara-filter-label {
    display: block; font-size: 0.7rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.06em;
    color: #94a3b8; margin-bottom: 0.2rem;
  }
  .ara-page .ara-filter-btn {
    display: inline-flex; align-items: center; justify-content: center; width: 100%;
    font-size: 0.78rem; font-weight: 500; padding: 0.34rem 0.75rem;
    border-radius: 0.4rem; border: none; background: #2563eb; color: #fff;
    cursor: pointer; transition: background 0.15s;
  }
  .ara-page .ara-filter-btn:hover { background: #1d4ed8; }

  /* 列ヘッダー */
  .ara-page .ara-header {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.06em; color: #94a3b8;
  }

  /* リスト */
  .ara-page .ara-list { display: flex; flex-direction: column; gap: 0.3rem; }

  /* カード = 1行 */
  .ara-page .ara-card {
    display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 0.5rem; padding: 0.35rem 0.75rem;
    transition: box-shadow 0.15s;
  }
  .ara-page .ara-card:hover { box-shadow: 0 1px 6px rgba(0,0,0,.07); }
  .ara-page .ara-card--required {
    border-left: 3px solid #f59e0b;
    background: linear-gradient(90deg, rgba(251,191,36,.06), transparent 40%);
  }

  /* 列幅定義 */
  .ara-page .ara-col { min-width: 0; }
  .ara-page .ara-col--user   { flex: 0 0 110px; }
  .ara-page .ara-col--date   { flex: 0 0 90px; }
  .ara-page .ara-col--kind   { flex: 0 0 100px; }
  .ara-page .ara-col--status { flex: 0 0 100px; }
  .ara-page .ara-col--reason { flex: 3 1 160px; }
  .ara-page .ara-col--handle { flex: 2 1 140px; }
  .ara-page .ara-col--action { flex: 0 0 80px; text-align: right; }

  /* モバイル用ラベル */
  .ara-page .ara-mobile-label {
    display: none; font-size: 0.68rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.06em;
    color: #94a3b8; margin-bottom: 0.15rem;
  }
  @media (max-width: 991.98px) {
    .ara-page .ara-card         { flex-direction: column; align-items: stretch; padding: 0.6rem 0.75rem; gap: 0.4rem; }
    .ara-page .ara-col          { flex: none !important; width: 100%; }
    .ara-page .ara-mobile-label { display: block; }
  }

  /* 付属スタイル */
  .ara-page .ara-code { font-size: 0.72rem; color: #94a3b8; margin-left: 0.2rem; }

  .ara-page .ara-kind-badge {
    display: inline-block; font-size: 0.72rem; font-weight: 500;
    padding: 0.2rem 0.55rem; border-radius: 9999px;
    background: #e0f2fe; color: #0369a1;
  }
  .ara-page .ara-kind-badge--unpaid-leave { background: #dbeafe; color: #1d4ed8; }

  .ara-page .ara-status {
    display: inline-flex; align-items: center;
    font-size: 0.72rem; font-weight: 500;
    padding: 0.2rem 0.55rem; border-radius: 9999px;
  }
  .ara-page .ara-status--done    { background: #dcfce7; color: #16a34a; }
  .ara-page .ara-status--pending { background: #fef9c3; color: #a16207; }

  .ara-page .ara-details-btn {
    display: inline-flex; align-items: center; white-space: nowrap;
    font-size: 0.75rem; font-weight: 500;
    padding: 0.25rem 0.65rem; border-radius: 0.4rem;
    border: 1px solid #e2e8f0; background: #f8fafc; color: #475569;
    text-decoration: none; transition: background 0.15s, border-color 0.15s;
  }
  .ara-page .ara-details-btn:hover { background: #f1f5f9; border-color: #cbd5e1; color: #1e293b; }

  .ara-page .ara-empty { text-align: center; color: #94a3b8; padding: 3rem 1rem; }
</style>
@endpush
