{{-- resources/views/expenses/edit.blade.php --}}
@extends('layouts.app')

@section('title', '交通費（表示のみ）')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
{{-- ✅ 自作CSS（キャッシュ破棄のためにバージョン付与） --}}
<link rel="stylesheet" href="{{ asset('css/expenses.css') }}?v={{ filemtime(public_path('css/expenses.css')) }}">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js"></script>
@endpush

@section('content')
<div class="page-wrap">
  <h1 class="mb-3">交通費（{{ $y }}年{{ $m }}月）</h1>

  {{-- ▼ 月選択フォーム --}}
  <form method="GET" class="mb-3 d-flex align-items-center gap-2" id="monthForm">
    <label for="monthPick" class="form-label m-0">対象月</label>
    <input type="month" id="monthPick" name="monthpick"
      class="form-control form-control-sm" style="width:170px"
      value="{{ sprintf('%04d-%02d', $y, $m) }}">
    <button id="monthSearchBtn" class="btn btn-sm btn-outline-primary" type="button">検索</button>

    <noscript>
      <input type="hidden" name="year" value="{{ $y }}">
      <input type="hidden" name="month" value="{{ $m }}">
      <button type="submit" class="btn btn-sm btn-outline-primary">検索</button>
    </noscript>
  </form>

  <div class="header-box mb-4">
    @if($hasReport)
    <div class="meta">
      <div>講師: <strong>{{ $report->employee_family_name }} {{ $report->employee_first_name }}</strong></div>
      <div>社員コード: <strong>{{ $report->employee_code }}</strong></div>
      <div>ステータス:
        <strong>{{ strtoupper($report->status->value ?? $report->status) }}</strong>
      </div>

      {{-- ▼ 定期券（この月に有効なものを右寄せで詳細表示） --}}
      @if(!empty($activePasses))
      <div class="total ms-auto text-end">
        @foreach($activePasses as $p)
        <div class="muted small">
          <strong>定期券:{{ $p['station_from'] }} → {{ $p['station_to'] }}</strong>
          <span class="muted">{{ $p['valid_range'] }}</span>
        </div>
        @endforeach
      </div>
      @endif

      {{-- ▼ 空白行（1行分の余白） --}}
      <div class="total w-100 mb-2">合計: <strong id="sumCost">{{ number_format($report->total_amount) }}</strong> 円</div>

    </div>
    <div class="mt-2 d-flex align-items-center gap-2">
      @if($report->status === \App\Enums\ExpenseReportStatus::DRAFT)
      <input type="date" id="pickDate" class="form-control form-control-sm" style="width: 160px;">
      <button id="addByDateBtn" class="btn btn-success btn-sm" type="button" disabled>＋指定日を追加</button>
      <button id="saveBtn" class="btn btn-primary btn-sm" type="button">保存</button>
      <div class="ms-auto"></div>
      <form method="POST" action="{{ route('expenses.submit', ['report' => $report->id]) }}"
        onsubmit="return confirm('この月の交通費を提出しますか？（提出後は編集できません）');">
        @csrf @method('PUT')
        <button type="submit" class="btn btn-warning btn-sm" id="submitBtn">提出</button>
      </form>
      @else
      <div class="ms-auto"></div>
      <span class="text-muted">提出済み（{{ optional($report->submitted_at)->format('Y-m-d H:i') }}）
        <br>If you need to correct, please contact XXX Dpt at 06-XXXX-XXXX.
      </span>
      @endif
    </div>
    @else
    <div class="p-2">
      <div class="muted">講師: <strong>{{ $user->name ?? '' }}</strong></div>
      <div class="mt-2 alert alert-secondary" role="alert" style="padding:8px 12px">
        対象レコードがございません（{{ $y }}年{{ $m }}月）。<br>
        上の「対象月」から別の月をお選びください。
      </div>
    </div>
    @endif
  </div>

  <div id="sheet"></div>

  {{-- ✅ ヘッダーの下など、見せたい場所に置く --}}
  <a href="https://world.jorudan.co.jp/mln/en/?sub_lang=nosub"
    class="btn btn-outline-secondary btn-sm"
    target="_blank" rel="noopener noreferrer">
    Open Jorudan (Japanese Transit Planer)
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
@push('scripts')
{{-- すでにあるCDNはそのまま --}}
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
  };
</script>
{{-- ✅ 外部ファイル --}}
<script src="{{ asset('js/expenses.js') }}?v={{ filemtime(public_path('js/expenses.js')) }}" defer></script>
@endpush
@endsection