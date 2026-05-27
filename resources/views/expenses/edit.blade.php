{{-- resources/views/expenses/edit.blade.php --}}
@extends('layouts.app')

@section('title', '交通費（表示のみ）')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
{{-- ✅ 自作CSS（キャッシュ破棄のためにバージョン付与） --}}
<link rel="stylesheet" href="{{ asset('css/expenses.css') }}?v={{ filemtime(public_path('css/expenses.css')) }}">
@endpush

{{-- ✅ 重複していた上側の @push('scripts') は削除済み --}}

@section('content')
<div class="page-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="mb-0">
          Commuting Expense（{{ $y }}/{{ $m }}）
      </h1>

      @php $me = auth()->user(); @endphp
      <div class="d-flex flex-wrap gap-2">
          @if($me->hasAnyRole(['admin','super_admin']))
              <a href="{{ route('routes.admin.create', $report->employee_code) }}"
                class="btn btn-outline-primary btn-sm btn-route-fixed">
                  ＋ Route Declaration
              </a>
          @else
              <a href="{{ route('routes.create') }}"
                class="btn btn-outline-primary btn-sm btn-route-fixed">
                  ＋ Route Declaration
              </a>
          @endif

          @if($me->hasAnyRole(['admin','super_admin']))
              <a href="{{ route('commuter_passes.admin.create', $report->employee_code) }}"
                class="btn btn-outline-primary btn-sm btn-route-fixed">
                  ＋ Commuter Pass
              </a>
          @else
              <a href="{{ route('commuter_passes.create') }}"
                class="btn btn-outline-primary btn-sm btn-route-fixed">
                  ＋ Commuter Pass
              </a>
          @endif
      </div>
  </div>

  {{-- ▼ 月選択フォーム --}}
  <form method="GET" class="mb-3 d-flex align-items-center gap-2" id="monthForm">
    <label for="monthPick" class="form-label m-0">Target Month</label>
    <input type="month" id="monthPick" name="monthpick"
      class="form-control form-control-sm" style="width:170px"
      value="{{ sprintf('%04d-%02d', $y, $m) }}">
    <button id="monthSearchBtn" class="btn btn-sm btn-outline-primary" type="button">Search</button>

    <noscript>
      <input type="hidden" name="year" value="{{ $y }}">
      <input type="hidden" name="month" value="{{ $m }}">
      <button type="submit" class="btn btn-sm btn-outline-primary">Search</button>
    </noscript>
  </form>

  <div class="header-box mb-1">
    @if($hasReport)
    <div class="meta">
      <div>Teacher: <strong>{{ $report->employee_family_name }} {{ $report->employee_first_name }}</strong></div>
      <div>Employee Code: <strong>{{ $report->employee_code }}</strong></div>
      <div>Status:
        <strong>{{ strtoupper($report->status->value ?? $report->status) }}</strong>
      </div>

      {{-- ▼ 定期券（この月に有効なものを右寄せで詳細表示） --}}
      @if(!empty($activePasses))
      <div class="total ms-auto text-end">
        @foreach($activePasses as $p)
        <div class="muted small">
          <strong>Commuting Pass: {{ $p['station_from'] }} → {{ $p['station_to'] }}</strong>
          <span class="muted">{{ $p['valid_range'] }}</span>
        </div>
        @endforeach
      </div>
      @endif

      {{-- ▼ 空白行（1行分の余白） --}}
      <div class="total w-100 mb-2">Total: <strong id="sumCost">{{ number_format($report->total_amount) }}</strong> Yen</div>

    </div>
    <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
      @if($report->status === \App\Enums\ExpenseReportStatus::DRAFT)
        {{-- ▼ 日付・ボタン類（横幅が狭い場合は縦並び） --}}
        <div class="d-flex flex-column flex-sm-row gap-2">
          <input type="date" id="pickDate" class="form-control form-control-sm" style="width: 160px;">
          <button id="addByDateBtn" class="btn btn-success btn-sm" type="button" disabled>＋Add Date</button>
          <button id="saveBtn" class="btn btn-primary btn-sm" type="button">Save</button>
        </div>

        <div class="ms-auto mt-2 mt-sm-0"></div>

        <div class="expense-edit-table-toolbar mt-2 mt-sm-0">
          <label for="expenseEditHeight" class="form-label m-0 small text-muted">Table Height</label>
          <select id="expenseEditHeight" class="form-select form-select-sm expense-edit-height-select">
            <option value="420">Compact</option>
            <option value="560">Standard</option>
            <option value="720">Tall</option>
            <option value="full">Full</option>
          </select>
        </div>

        {{-- ✅ JS不要でモーダルを開く（data 属性方式） --}}
        <button type="button"
          class="btn btn-warning btn-sm mt-2 mt-sm-0"
          data-bs-toggle="modal"
          data-bs-target="#confirmSubmitModal">
          Submit
        </button>
      @else
        <div class="ms-auto mt-2 mt-sm-0 d-flex align-items-center gap-2">

          @if(auth()->user()?->hasAnyRole(['admin','super_admin']))
            <span class="text-muted small">
              Submitted（{{ optional($report->submitted_at)->format('Y-m-d H:i') }}）
            </span>

            <div class="expense-edit-table-toolbar">
              <label for="expenseEditHeight" class="form-label m-0 small text-muted">Table Height</label>
              <select id="expenseEditHeight" class="form-select form-select-sm expense-edit-height-select">
                <option value="420">Compact</option>
                <option value="560">Standard</option>
                <option value="720">Tall</option>
                <option value="full">Full</option>
              </select>
            </div>

            {{-- 管理者のみ Unsubmit ボタン表示 --}}
            <form method="POST" action="{{ route('expenses.unsubmit', $report) }}">
              @csrf
              @method('PATCH')
              <button type="submit" class="btn btn-outline-danger btn-sm">
                Unsubmit
              </button>
            </form>
          @else
            {{-- 一般ユーザーは従来通り表示のみ --}}
            <span class="text-muted">
              Submitted（{{ optional($report->submitted_at)->format('Y-m-d H:i') }}）
            </span>

            <div class="expense-edit-table-toolbar">
              <label for="expenseEditHeight" class="form-label m-0 small text-muted">Table Height</label>
              <select id="expenseEditHeight" class="form-select form-select-sm expense-edit-height-select">
                <option value="420">Compact</option>
                <option value="560">Standard</option>
                <option value="720">Tall</option>
                <option value="full">Full</option>
              </select>
            </div>
          @endif
        </div>
      @endif
    </div>
    @else
    <div class="p-2">
      <div class="muted">Teacher: <strong>{{ $user->name ?? '' }}</strong></div>
      <div class="mt-2 alert alert-secondary" role="alert" style="padding:8px 12px">
        No matching records found.（{{ $y }}/{{ $m }}）.<br>
        Please select a different month from “Target Month” above.
      </div>
    </div>
    @endif
  </div>

  {{-- ▼ ルート申請表示カード: 'showMore' => trueでボタンをactive --}}
  @include('routes.showCard', ['routeDecl' => $routeDecl, 'showMore' => true])

  <div id="sheetScroll">
    <div id="sheet"></div>
  </div>

  {{-- 便利リンク --}}
  <div class="mt-2 d-flex gap-2">
    <a href="https://world.jorudan.co.jp/mln/en/?sub_lang=nosub"
      class="btn btn-outline-secondary btn-sm"
      target="_blank" rel="noopener noreferrer">
      Open Jorudan (Japanese Transit Planner)
    </a>
    <a href="https://www.google.com/maps/"
      class="btn btn-outline-secondary btn-sm"
      target="_blank" rel="noopener noreferrer">
      Open Google Maps
    </a>
    <a href="https://map.yahoo.co.jp/"
      class="btn btn-outline-secondary btn-sm"
      target="_blank" rel="noopener noreferrer">
      Open Yahoo Maps
    </a>
  </div>

  {{-- ✅ Toast container（通知の表示先。Bootstrap 5 前提） --}}
  <div id="toastContainer"
    class="toast-container position-fixed bottom-0 end-0 p-3"
    style="z-index:1056" {{-- 保存ボタン帯(998)＋ボタン(999)より上 --}}
    aria-live="polite" aria-atomic="true"></div>

  {{-- ✅ 削除確認モーダル --}}
  <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark py-2 custom-orange-header">
          <h6 class="modal-title" id="confirmDeleteLabel">Delete Confirmation</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        </div>
        <div class="modal-body small">
          Are you sure you want to delete this row?
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="confirmDeleteYes" class="btn btn-danger btn-sm">Delete</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ✅ 提出確認モーダル（レポートあり & DRAFT のときだけ描画） --}}
@if($hasReport && $report?->status === \App\Enums\ExpenseReportStatus::DRAFT)
<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-labelledby="confirmSubmitLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white py-2">
        <h6 class="modal-title" id="confirmSubmitLabel">Submit Confirmation </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body small">
        Are you sure you want to submit the expense report? <br><br>
        Once submitted, you will not be able to edit the report.
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>

        {{-- 実際の提出フォーム（ここは hasReport 確定なので $report->id を安全に参照できる） --}}
        <form id="submitForm"
          method="POST"
          action="{{ route('expenses.submit', ['report' => $report->id]) }}">
          @csrf @method('PUT')
          <button type="submit" class="btn btn-danger btn-sm">Submit</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

@push('styles')
<style>
  .btn-route-fixed {
    width: 150px;        /* 好きな幅に調整してOK */
    text-align: center;  /* 文字を中央揃え */
    white-space: nowrap; /* 改行しないように（任意） */
  }
</style>
@endpush

@push('scripts')
{{-- ✅ 必要なCDNはここで1回だけ --}}
<script src="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js"></script>

{{-- ✅ Blade→JS への受け渡し（最小限） --}}
<script>
  window.EXPENSES_BOOTSTRAP = {
    hasReport: @json($hasReport),
    csrfToken: @json(csrf_token()),
    reportId: @json($report?->id),
    year: @json($y),
    month: @json($m),
    initialRows: @json($rows),
    eventOnMap: @json($eventOnMap ?? []),
    passActiveMap: @json($passActiveMap ?? []),

    // ★ 提出後ロック（DRAFT以外ならtrue）
    isLocked: @json($hasReport && $report->status !==\App\Enums\ExpenseReportStatus::DRAFT),
  };
</script>

{{-- ✅ 外部ファイル（ページ固有ロジック） --}}
<script src="{{ asset('js/expenses.js') }}?v={{ filemtime(public_path('js/expenses.js')) }}" defer></script>
@endpush
