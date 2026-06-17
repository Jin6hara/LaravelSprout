{{-- 管理者向けに全ユーザーの交通費レポートを一覧表示するビュー --}}
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
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="mb-0">
      Commuting Expense Reports（{{ $y }}/{{ $m }}）
    </h1>

    {{-- 右側：管理者用 Generate / Cleanup ボタン群 --}}
    @can('manage', \App\Models\ExpenseReport::class)
    <div class="d-flex align-items-center gap-2">

      {{-- ★ 自動生成（対象月の全ユーザー分のレポート＆日別行を作成） --}}
      <form method="POST" action="{{ route('expenses.generateMonthly') }}" class="mb-0">
        @csrf
        <input type="hidden" name="year" value="{{ $y }}">
        <input type="hidden" name="month" value="{{ $m }}">

        <button type="submit"
          class="btn btn-sm btn-outline-secondary"
          onclick="return confirm('Generate expense rows for all active users for {{ sprintf('%04d-%02d', $y, $m) }} ?');">
          Generate
        </button>
      </form>

      {{-- ★ 空行削除（note=null & cost=0） --}}
      <form method="POST" action="{{ route('expenses.cleanupEmpty') }}" class="mb-0">
        @csrf
        <input type="hidden" name="year" value="{{ $y }}">
        <input type="hidden" name="month" value="{{ $m }}">

        <button type="submit"
          class="btn btn-sm btn-outline-danger"
          onclick="return confirm('Delete empty expenses (note is NULL & cost = 0) for {{ sprintf('%04d-%02d', $y, $m) }} ?');">
          Cleanup
        </button>
      </form>

    </div>
    @endcan
  </div>

  {{-- ▼ 月選択 + 関連ページボタン行 --}}
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">

    {{-- 月選択フォーム --}}
    <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-0" id="monthForm">
      <label for="monthPick" class="form-label m-0">Target Month</label>

      <input type="month" id="monthPick" name="monthpick"
        class="form-control form-control-sm" style="width:170px"
        value="{{ sprintf('%04d-%02d', $y, $m) }}">

      <button id="monthSearchBtn" class="btn btn-sm btn-outline-primary" type="button">
        Search
      </button>
    </form>

    @can('manage', \App\Models\ExpenseReport::class)
    <a class="btn btn-sm mb-2 btn-outline-success ms-lg-auto" href="{{ route('commuter.advisor.index') }}">
      Commuter Pass Advisor
    </a>
    @endcan

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

      <div class="expense-table-toolbar ms-auto">
        <label for="expenseReportHeight" class="form-label m-0 small text-muted">Table Height</label>
        <select id="expenseReportHeight" class="form-select form-select-sm expense-height-select">
          <option value="420">Compact</option>
          <option value="560">Standard</option>
          <option value="720">Tall</option>
          <option value="full">Full</option>
        </select>
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
