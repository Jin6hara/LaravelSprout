@extends('layouts.app')

@section('title', '交通費レポート一覧（管理者）')

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
  <style>
    .page-wrap { max-width: 1100px; margin: 20px auto; }
    .header-box {
      background: #f8f9fa; padding: 12px 16px; border-radius: 8px; border: 1px solid #78a3faff;
    }
    .header-box .meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 14px; }
    #sheet { width: 100%; height: auto; }

    h1 { margin-bottom: 1.5rem; }
    .jspreadsheet tbody td { font-size: 0.95rem; }
    
    /* ▼ シート内リンクの下線を全消し */
    #sheet a { 
      text-decoration: none !important;
    }
    /* Detailsのボタンを見やすく */
    #sheet .btn.btn-sm.btn-outline-primary {
      padding: 2px 8px;
      line-height: 0.9;
    }
  </style>
@endpush

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js"></script>
@endpush

@section('content')


<div class="page-wrap">
  <h1>交通費レポート一覧（{{ $y }}年{{ $m }}月）</h1>

  {{-- ▼ 月選択フォーム --}}
  <form method="GET" class="mb-3 d-flex align-items-center gap-2" id="monthForm">
    <label for="monthPick" class="form-label m-0">対象月</label>
    <input type="month" id="monthPick" name="monthpick"
      class="form-control form-control-sm" style="width:170px"
      value="{{ sprintf('%04d-%02d', $y, $m) }}">
    <button id="monthSearchBtn" class="btn btn-sm btn-outline-primary" type="button">検索</button>
  </form>

    <div class="header-box mb-4">
      <div class="meta w-100" style="gap:24px">
        <div>総人数: <strong>{{ number_format($summary['count']) }}</strong> 人</div>
        <div>提出人数: <strong>{{ number_format($summary['submitted']) }}</strong> 人</div>
        <div>未提出人数: <strong>{{ number_format($summary['not_submitted']) }}</strong> 人</div>
        
        {{-- 合計（提出済を主役、全体は参考） --}}
        <div class="total">
          提出済合計: <strong id="sumSubmitted">{{ number_format($summary['sum_submitted']) }}</strong> 円
          <span class="muted ms-2">(全体: <span id="sumAll">{{ number_format($summary['sum_all']) }}</span> 円)</span>
          @if(($summary['sum_approved'] ?? 0) > 0)
            <span class="muted ms-2">/ 承認済: <span id="sumApproved">{{ number_format($summary['sum_approved']) }}</span> 円</span>
          @endif
        </div>

        <div class="ms-auto d-flex align-items-center" style="gap:8px; flex-wrap:wrap">
          @foreach($summary['by_status'] as $st => $cnt)
            @php
              $cls = match (strtolower($st)) {
                'draft'     => 'badge-draft',
                'submitted' => 'badge-submitted',
              };
            @endphp
            <span class="badge {{ $cls }}" title="{{ $st }}">{{ strtoupper($st) }}: {{ $cnt }}</span>
          @endforeach
        </div>
      </div>
    </div>

  {{-- ▼ テーブル --}}
  <div id="sheet"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // --- 月検索（そのまま） ---
  const monthInput = document.getElementById('monthPick');
  const btn        = document.getElementById('monthSearchBtn');
  function doSearch() {
    const v = monthInput?.value || '';
    if (!/^\d{4}-\d{2}$/.test(v)) { alert('対象月を選択してください。'); return; }
    const [yy, mm] = v.split('-');
    const url = new URL(window.location.href);
    url.searchParams.set('year', yy);
    url.searchParams.set('month', Number(mm));
    window.location = url.toString();
  }
  btn?.addEventListener('click', (e) => { e.preventDefault(); doSearch(); });
  monthInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });

  // --- 表データ（そのまま） ---
  const rows = @json($rows);
  const yy = {{ (int)$y }};
  const mm = {{ (int)$m }};

  // DetailsボタンHTMLを生成
  function detailsBtn(code) {
    const safe = encodeURIComponent(String(code ?? ''));
    const href = `/expenses/${safe}/edit?year=${yy}&month=${mm}`;
    // aタグをBootstrap風ボタンに（別タブで開く）
    return `<a href="${href}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Details</a>`;
  }
  // データー挿入（順番に注意）
  const matrix = rows.map(r => ([
    detailsBtn(r.employee_code),            // 0: Details（ボタン）
    r.employee_code ?? '',
    r.family_name   ?? '', 
    r.first_name    ?? '',
    Number(r.total_amount ?? 0),
    (r.status || '').toUpperCase(),
    r.submitted_at  ?? ''
  ]));

  // === v5 正しい初期化：worksheets を使う ===
  const el = document.getElementById('sheet');
  const sheet = jspreadsheet(el, {
    worksheets: [{
      data: matrix,
      // セル一つしか効かない
      style: {
        '':'color:red; font-weight:bold;'
      },
      columns: [
        { title: 'Details',       width: 90,  readOnly: true, type: 'html' }, // ボタン列
        { title: 'Employee Code', width: 150, readOnly: true }, // ← html
        { title: 'Family Name',   width: 150, readOnly: true },
        { title: 'First (Middle) Name',    width: 220, readOnly: true },
        { title: 'Total (JPY)',   width: 140, readOnly: true, type: 'numeric', mask: '#,##0' },
        { title: 'Status',        width: 140, readOnly: true },
        { title: 'Submitted At',  width: 157, readOnly: true },
      ],
      allowInsertRow: false,
      allowManualInsertRow: false,
      allowDeleteRow: false,
      allowInsertColumn: false,
      allowDeleteColumn: false,
      allowRenameColumn: false,
      allowComments: false,
      allowSaving: false,
      freezeColumns: 1,
      tableOverflow: false, //影
      tableHeight: '470px',
      // v5 での簡易ソートはヘッダクリックで可（columns 定義に依存）
      // 必要に応じて search/tabs なども後で追加可能
    }],
  });

  // 参考：合計再計算（今はPHPで済ませている）
  const total = sheet[0].getColumnData(3).reduce((a,v)=>a+(+v||0),0);
});
</script>

@endsection
