@extends('layouts.app')

@section('title', '交通費レポート一覧（管理者）')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
{{-- ✅ 自作CSS（キャッシュ破棄のためにバージョン付与） --}}
<link rel="stylesheet" href="{{ asset('css/expenseReport.css') }}?v={{ filemtime(public_path('css/expenseReport.css')) }}">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js"></script>
{{-- ✅ 自作JS（キャッシュ破棄のためにバージョン付与） --}}
<script src="{{ asset('js/expenseReport.js') }}?v={{ filemtime(public_path('js/expenseReport.js')) }}"></script>
@endpush

@section('content')
<div class="page-wrap">
  <h1>Commuting Expense Reports（{{ $y }}/{{ $m }}）</h1>

  {{-- ▼ 月選択 + 自動生成ボタン行 --}}
  <div class="d-flex justify-content-between align-items-center mb-3 gap-2">

    {{-- 左側：月選択フォーム --}}
    <form method="GET" class="d-flex align-items-center gap-2" id="monthForm">
      <label for="monthPick" class="form-label m-0">Target Month</label>
      <input type="month" id="monthPick" name="monthpick"
        class="form-control form-control-sm" style="width:170px"
        value="{{ sprintf('%04d-%02d', $y, $m) }}">
      <button id="monthSearchBtn" class="btn btn-sm btn-outline-primary" type="button">Search</button>
      <a class="btn btn-sm btn-outline-success ms-2" href="{{ route('commuter.advisor.index') }}">
        Commuter Pass Advisor
      </a>
    </form>

    {{-- 右側：管理者用 Generate ボタン --}}
    @if(auth()->user()?->hasAnyRole(['admin','super_admin']))
    <form method="POST" action="{{ route('expenses.generateMonthly') }}" class="d-flex">
      @csrf
      <input type="hidden" name="year" value="{{ $y }}">
      <input type="hidden" name="month" value="{{ $m }}">
      <button type="submit"
        class="btn btn-sm btn-outline-secondary"
        onclick="return confirm('Generate expense rows for all active users for {{ sprintf('%04d-%02d', $y, $m) }} ?');">
        Generate
      </button>
    </form>
    @endif
  </div>

  <div class="header-box mb-4">
    <div class="meta w-100" style="gap:24px">
      <div>Total Reports: <strong>{{ number_format($summary['count'] ?? 0) }}</strong></div>
      <div>Submitted: <strong>{{ number_format($summary['submitted'] ?? 0) }}</strong></div>
      <div>Not Submitted: <strong>{{ number_format($summary['not_submitted'] ?? 0) }}</strong></div>

      <div class="total">
        Total Submitted Amount: <strong id="sumSubmitted">{{ number_format($summary['sum_submitted'] ?? 0) }}</strong> Yen
        <span class="muted ms-2">(Total Amount: <span id="sumAll">{{ number_format($summary['sum_all'] ?? 0) }}</span> Yen)</span>
        @if(($summary['sum_approved'] ?? 0) > 0)
        <span class="muted ms-2">/ 承認済: <span id="sumApproved">{{ number_format($summary['sum_approved']) }}</span> Yen</span>
        @endif
      </div>

      <div class="ms-auto d-flex align-items-center" style="gap:8px; flex-wrap:wrap">
        @foreach(($summary['by_status'] ?? []) as $st => $cnt)
        @php
        $cls = match (strtolower($st)) { 'draft' => 'badge-draft', 'submitted' => 'badge-submitted', default => 'badge-secondary', };
        @endphp
        <span class="badge {{ $cls }}" title="{{ $st }}">{{ strtoupper($st) }}: {{ $cnt }}</span>
        @endforeach
      </div>

      {{-- ▼ レコード無しメッセージ（count=0 の時のみ表示） --}}
      @if(($summary['count'] ?? 0) === 0)
      <div class="w-100 mt-2">
        <div class="alert alert-secondary py-2 px-3 small" role="alert">
          No matching records found.（{{ $y }}/{{ $m }}）.<br>
          Please select a different month from “Target Month” above.
        </div>
      </div>
      @endif
    </div>
  </div>

  {{-- ▼ テーブル --}}
  <div id="sheet"></div>

  {{-- ✅ データ受け渡し用のJSON（JSロジックは外部ファイル） --}}
  <script id="expenseReportData" type="application/json"
    data-year="{{ (int)$y }}"
    data-month="{{ (int)$m }}">
    @json($rows)
  </script>
</div>
@endsection