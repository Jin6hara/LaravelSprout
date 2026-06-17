{{-- 特定ユーザーの欠勤・休暇レポートを一覧表示するビュー --}}
{{-- resources/views/calendar/absenceReport.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="ar-page">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h2 class="mb-0 fw-semibold">Absence Report</h2>
      <div class="text-muted small mt-1">
        User: <span class="fw-semibold">{{ $user->name ?? trim(($user->family_name ?? '').' '.($user->first_name ?? '')) }}</span>
      </div>
    </div>
    @role('admin|super_admin')
    <span class="ar-badge ar-badge--admin">Admin View</span>
    @else
    <span class="ar-badge ar-badge--self">My Absences</span>
    @endrole
  </div>

  {{-- 列ヘッダー（デスクトップのみ） --}}
  <div class="ar-header d-none d-lg-flex">
    <div class="ar-col ar-col--date">Date</div>
    <div class="ar-col ar-col--kind">Kind</div>
    <div class="ar-col ar-col--status">Status</div>
    <div class="ar-col ar-col--reason">Reason</div>
    <div class="ar-col ar-col--handle">Handle Type</div>
    <div class="ar-col ar-col--attachment">Attachment</div>
    <div class="ar-col ar-col--action"></div>
  </div>

  {{-- List --}}
  <div class="ar-list">

    @forelse($rows as $row)
    @php($leave = $row['leave'])

    <div class="ar-card {{ $row['needsReport'] ? 'ar-card--required' : '' }}">

      {{-- Date --}}
      <div class="ar-col ar-col--date">
        <span class="ar-mobile-label">Date</span>
        <span class="fw-semibold">{{ $row['dateMain'] }}</span>
        @if($row['dateSub'])
        <span class="text-muted" style="font-size:0.72rem;">〜{{ $row['dateSub'] }}</span>
        @endif
      </div>

      {{-- Kind --}}
      <div class="ar-col ar-col--kind">
        <span class="ar-mobile-label">Kind</span>
        <span class="ar-kind-badge ar-kind-badge--{{ Str::slug($row['kindLabel']) }}">{{ $row['kindLabel'] }}</span>
      </div>

      {{-- Status --}}
      <div class="ar-col ar-col--status">
        <span class="ar-mobile-label">Status</span>
        @if($row['statusText'] === 'Submitted')
        <span class="ar-status ar-status--done"><i class="bi bi-check-circle-fill me-1"></i>Submitted</span>
        @elseif($row['statusText'] === 'Required')
        <span class="ar-status ar-status--pending"><i class="bi bi-exclamation-circle-fill me-1"></i>Required</span>
        @else
        <span class="text-muted">—</span>
        @endif
      </div>

      {{-- Reason --}}
      <div class="ar-col ar-col--reason">
        <span class="ar-mobile-label">Reason</span>
        @if($row['needsReport'])
        <textarea name="reason" form="{{ $row['formId'] }}"
          class="form-control form-control-sm" rows="1"
          placeholder="Enter reason (required)" required></textarea>
        @else
        <textarea class="form-control form-control-sm" rows="1" readonly>{{ $leave->reason }}</textarea>
        @endif
      </div>

      {{-- Handle Type --}}
      <div class="ar-col ar-col--handle">
        <span class="ar-mobile-label">Handle Type</span>
        @if($row['needsReport'])
        <select name="handle_type" form="{{ $row['formId'] }}"
          class="form-select form-select-sm" required>
          <option value="">— Select —</option>
          @foreach($handleTypeOptions as $val => $label)
          <option value="{{ $val }}">{{ $label }}</option>
          @endforeach
        </select>
        @else
        <input type="text" class="form-control form-control-sm" value="{{ $row['handleLabel'] }}" readonly>
        @endif
      </div>

      {{-- Attachment --}}
      <div class="ar-col ar-col--attachment">
        <span class="ar-mobile-label">Attachment</span>
        @if($row['needsReport'])
        <input type="file" name="attachment" form="{{ $row['formId'] }}" class="form-control form-control-sm">
        @elseif($leave->attachment)
        <a href="{{ route('absence.download', $leave) }}"
           class="ar-attachment-link"
           title="{{ $leave->attachment->original_name ?? 'View Attachment' }}">
          <i class="bi bi-paperclip me-1"></i>
          <span class="text-truncate">{{ $leave->attachment->original_name ?? 'View Attachment' }}</span>
        </a>
        @else
        <span class="text-muted small">—</span>
        @endif
      </div>

      {{-- Action --}}
      <div class="ar-col ar-col--action">
        @if($row['needsReport'])
        <form id="{{ $row['formId'] }}" method="POST"
          action="{{ route('report.update', $leave) }}"
          enctype="multipart/form-data">
          @csrf @method('PUT')
          <button type="submit" class="ar-submit-btn">
            <i class="bi bi-send me-1"></i>Submit
          </button>
        </form>
        @else
        <div class="ar-done-mark"><i class="bi bi-check-lg"></i></div>
        @endif
      </div>

    </div>
    @empty
    <div class="ar-empty">
      <i class="bi bi-inbox fs-2 d-block mb-2"></i>No records.
    </div>
    @endforelse

  </div>

</div>
@endsection

@push('styles')
<style>
  /* ===== すべて .ar-page 以下にスコープ ===== */

  .ar-page .ar-badge {
    font-size: 0.75rem; font-weight: 500;
    padding: 0.3rem 0.75rem; border-radius: 9999px;
  }
  .ar-page .ar-badge--admin { background: #dbeafe; color: #1d4ed8; }
  .ar-page .ar-badge--self  { background: #f1f5f9; color: #475569; }

  /* 列ヘッダー */
  .ar-page .ar-header {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.06em; color: #94a3b8;
  }

  /* リスト */
  .ar-page .ar-list { display: flex; flex-direction: column; gap: 0.3rem; }

  /* カード = 1行 */
  .ar-page .ar-card {
    display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.35rem 0.75rem;
    transition: box-shadow 0.15s;
  }
  .ar-page .ar-card:hover { box-shadow: 0 1px 6px rgba(0,0,0,.07); }
  .ar-page .ar-card--required {
    border-left: 3px solid #f59e0b;
    background: linear-gradient(90deg, rgba(251,191,36,.06), transparent 40%);
  }

  /* 列幅定義（デスクトップ） */
  .ar-page .ar-col { min-width: 0; }
  .ar-page .ar-col--date       { flex: 0 0 90px; }
  .ar-page .ar-col--kind       { flex: 0 0 100px; }
  .ar-page .ar-col--status     { flex: 0 0 100px; }
  .ar-page .ar-col--reason     { flex: 2 1 160px; }
  .ar-page .ar-col--handle     { flex: 2 1 140px; }
  .ar-page .ar-col--attachment { flex: 1 1 120px; }
  .ar-page .ar-col--action     { flex: 0 0 80px; text-align: right; }

  /* モバイル用ラベル（lg 以上では非表示） */
  .ar-page .ar-mobile-label {
    display: none;
    font-size: 0.68rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.06em; color: #94a3b8; margin-bottom: 0.15rem;
  }
  @media (max-width: 991.98px) {
    .ar-page .ar-card        { flex-direction: column; align-items: stretch; padding: 0.6rem 0.75rem; gap: 0.4rem; }
    .ar-page .ar-col         { flex: none !important; width: 100%; }
    .ar-page .ar-mobile-label { display: block; }
  }

  /* Kind バッジ */
  .ar-page .ar-kind-badge {
    display: inline-block; font-size: 0.72rem; font-weight: 500;
    padding: 0.2rem 0.55rem; border-radius: 9999px;
    background: #e0f2fe; color: #0369a1;
  }
  .ar-page .ar-kind-badge--unpaid-leave { background: #dbeafe; color: #1d4ed8; }

  /* Status */
  .ar-page .ar-status {
    display: inline-flex; align-items: center;
    font-size: 0.72rem; font-weight: 500;
    padding: 0.2rem 0.55rem; border-radius: 9999px;
  }
  .ar-page .ar-status--done    { background: #dcfce7; color: #16a34a; }
  .ar-page .ar-status--pending { background: #fef9c3; color: #a16207; }

  /* 添付ファイルリンク */
  .ar-page .ar-attachment-link {
    display: inline-flex; align-items: center; max-width: 100%;
    font-size: 0.75rem; color: #2563eb; text-decoration: none;
    border: 1px solid #bfdbfe; border-radius: 0.35rem;
    padding: 0.2rem 0.5rem; background: #eff6ff; overflow: hidden;
  }
  .ar-page .ar-attachment-link:hover { background: #dbeafe; }

  /* Submit ボタン */
  .ar-page .ar-submit-btn {
    display: inline-flex; align-items: center; white-space: nowrap;
    font-size: 0.78rem; font-weight: 500;
    padding: 0.28rem 0.75rem; border-radius: 0.4rem; border: none;
    background: #2563eb; color: #fff; cursor: pointer; transition: background 0.15s;
  }
  .ar-page .ar-submit-btn:hover { background: #1d4ed8; }

  /* 完了マーク */
  .ar-page .ar-done-mark {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.6rem; height: 1.6rem; border-radius: 50%;
    background: #f1f5f9; color: #94a3b8; font-size: 0.85rem;
  }

  /* 空状態 */
  .ar-page .ar-empty { text-align: center; color: #94a3b8; padding: 3rem 1rem; }
</style>
@endpush
