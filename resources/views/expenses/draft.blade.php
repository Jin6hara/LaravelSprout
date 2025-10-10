{{-- resources/views/expenses/edit.blade.php --}}
@extends('layouts.app')

@section('title', '交通費（表示のみ）')

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
  <style>
    .page-wrap { max-width: 1100px; margin: 20px auto; }
    .header-box {
      background: #f8f9fa; padding: 12px 16px; border-radius: 8px; border: 1px solid #78a3faff;
    }
    .header-box .meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 14px; }
    .total { font-weight: 700; }
    .muted { color: #6b7280; }
    #sheet { width: 100%; max-width: 100%; height: auto; margin: 0 auto; }

    /* 選択枠を見やすく（薄緑上でも視認性UP）*/
    .jexcel_selection div {
      border: 2px solid #2a8a2a;
    }
  </style>
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
        <div>講師: <strong>{{ $report->employee_family_name }} {{ $report->employee_first_middle_name }}</strong></div>
        <div>社員コード: <strong>{{ $report->employee_code }}</strong></div>
        <div>ステータス:
          <strong>{{ strtoupper($report->status->value ?? $report->status) }}</strong>
        </div>
        <div class="total">合計: <strong id="sumCost">{{ number_format($report->total_amount) }}</strong> 円</div>
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
          <span class="text-muted">提出済み（{{ optional($report->submitted_at)->format('Y-m-d H:i') }}）</span>
        @endif
      </div>
    @else
      <div class="p-2">
        <div class="muted">講師: <strong>{{ $user->family_name ?? '' }} {{ $user->first_name ?? '' }}</strong></div>
        <div class="mt-2 alert alert-secondary" role="alert" style="padding:8px 12px">
          対象レコードがございません（{{ $y }}年{{ $m }}月）。<br>
          上の「対象月」から別の月をお選びください。
        </div>
      </div>
    @endif
  </div>

  <div id="sheet"></div>
</div>

{{-- 月検索 --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
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
});
</script>

{{-- ▼ JSpreadsheet 初期化＋保存／指定日追加（seq計算＆並び替え）＋ True 行のみ薄緑 --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!@json($hasReport)) return; // レポート無い月は何もしない

  const eventOnMap = @json($eventOnMap ?? {});
  const csrfToken   = @json(csrf_token());
  const reportId    = @json($report?->id);
  const year        = @json($y);
  const month       = @json($m);
  const initialRows = @json($rows);

  // サーバから渡されていればそれを使用（なければ空マップ）
  const eventOnMap  = @json($eventOnMap ?? []);

  // 背景色
  const ACTIVE_BG  = '#eaffea'; // Trueの日のみ
  const DEFAULT_BG = '#ffffff'; // それ以外

  // 英語の曜日
  function enWeekday(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d)) return '';
    return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()];
  }

  // TripType Enum
  const tripTypeOptions = [
    { id: 'round_trip', name: 'Round Trip' },
    { id: 'one_way',    name: 'One Way' },
  ];

  // 初期データ行（表示列＋非表示の内部列）
  // 列: Date / Day / From / To / Amount / TripType(ENUM値) / Note / _id / _seq
  const matrix = initialRows.map(r => {
    const date = r.expense_date || '';
    return [
      date,
      enWeekday(date),
      r.station_from || '',
      r.station_to   || '',
      Number.isFinite(r.cost) ? r.cost : 0,
      r.trip_type || '',
      r.note || '',
      r.id ?? '',
      (r.seq ?? 100)
    ];
  });

  // === シート生成 ===
  const sheet = jspreadsheet(document.getElementById('sheet'), {
    worksheets: [
      {
        data: matrix,
        columns: [
          { title:'Date',           type:'text',     width:120, readOnly:true  }, // 0
          { title:'Day',            type:'text',     width:70,  readOnly:true  }, // 1
          { title:'From',           type:'text',     width:160                    }, // 2
          { title:'To',             type:'text',     width:160                    }, // 3
          { title:'Amount (JPY)',   type:'numeric',  width:130, mask:'#,##0'     }, // 4
          { title:'Trip Type',      type:'dropdown', width:140, source: tripTypeOptions }, // 5
          { title:'Note',           type:'text',     width:220                    }, // 6
          { title:'_id',            type:'text',     visible:false                }, // 7
          { title:'_seq',           type:'numeric',  visible:false                }, // 8
        ],
        minDimensions: [9, Math.max(matrix.length, 1)],

        // True/False に応じて背景色を切替
        updateTable: function(instance, cell, col, row, val, label, cellName) {
          if (row >= 0 && col >= 0) {
            const date = instance.getValueFromCoords(0, row); // 0列目がDate
            cell.style.backgroundColor = eventOnMap[date] ? ACTIVE_BG : DEFAULT_BG;
          }
        },

        // 選択行の記憶（指定日追加の挿入ヒント）
        onselection: function(el, column, row) {
          lastSelectedRow = (typeof row === 'number') ? row : null;
        }
      }
    ]
  });

  // 便利関数：現在の全行データをオブジェクト配列に
  function readCurrentRows() {
    const data = sheet[0].getData(false); // 各セルの生値
    return data.map(arr => ({
      date:  arr[0] || '',
      day:   arr[1] || '',
      from:  arr[2] || '',
      to:    arr[3] || '',
      cost:  (arr[4] === '' || arr[4] == null) ? 0 : Number(String(arr[4]).replace(/,/g,'')),
      trip:  arr[5] || '', // dropdown は id が入る（round_trip / one_way）
      note:  arr[6] || '',
      id:    arr[7] || '',
      seq:   (arr[8] === '' || arr[8] == null) ? 100 : Number(arr[8]),
    })).filter(r => r.date); // 日付なしは無視
  }

  // ソート：第一キー=Date(昇順)、第二キー=seq(昇順)
  function sortRowsByDateThenSeq(rows) {
    return rows.slice().sort((a, b) => {
      if (a.date < b.date) return -1;
      if (a.date > b.date) return 1;
      return (a.seq - b.seq);
    });
  }

  // 再描画：rows(オブジェクト配列) → シートへ反映
  function renderRows(rows) {
    const newMatrix = rows.map(r => [
      r.date,
      enWeekday(r.date),
      r.from || '',
      r.to   || '',
      r.cost || 0,
      r.trip || '',
      r.note || '',
      r.id   || '',
      r.seq  ?? 100,
    ]);
    sheet[0].setData(newMatrix);
  }

  // 既存IDの集合（更新判定用）
  const initialIdSet = new Set(initialRows.map(r => String(r.id)));

  // === 指定日追加ロジック ===
  const pickDateEl   = document.getElementById('pickDate');
  const addByDateBtn = document.getElementById('addByDateBtn');
  let lastSelectedRow = null;

  function isValidDateStr(yyyy_mm_dd) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(yyyy_mm_dd)) return false;
    const [yy, mm, dd] = yyyy_mm_dd.split('-').map(Number);
    const d = new Date(yyyy_mm_dd + 'T00:00:00');
    if (isNaN(d)) return false;
    return (yy === Number(year) && mm === Number(month) && dd >= 1 && dd <= 31);
  }
  function enableAddButtonIfValid() {
    const v = pickDateEl?.value || '';
    addByDateBtn.disabled = !isValidDateStr(v);
  }
  pickDateEl?.addEventListener('input', enableAddButtonIfValid);
  enableAddButtonIfValid();

  // seq 決定
  function decideSeqForDate(targetDate, rows, hintAfterSeq = null) {
    const same = rows.filter(r => r.date === targetDate).sort((a,b)=>a.seq-b.seq);
    if (same.length === 0) return 1024;
    const maxSeq = same[same.length - 1].seq ?? 100;
    if (hintAfterSeq == null) return maxSeq + 1024;
    const next = same.find(r => r.seq > hintAfterSeq);
    if (next && (next.seq - hintAfterSeq) > 1) {
      return Math.floor((hintAfterSeq + next.seq) / 2);
    }
    return maxSeq + 1024;
  }

  // 追加ボタン
  addByDateBtn?.addEventListener('click', () => {
    const dateStr = pickDateEl?.value || '';
    if (!isValidDateStr(dateStr)) { alert('この月内の日付を選択してください。'); return; }

    const rows = readCurrentRows();

    let hintAfterSeq = null;
    if (lastSelectedRow != null) {
      const selected = rows[lastSelectedRow];
      if (selected && selected.date === dateStr) hintAfterSeq = selected.seq ?? null;
    }

    const newSeq = decideSeqForDate(dateStr, rows, hintAfterSeq);

    const newRow = {
      date: dateStr,
      day:  enWeekday(dateStr),
      from: '',
      to:   '',
      cost: 0,
      trip: '',
      note: '',
      id:   '',
      seq:  newSeq
    };

    const updated = sortRowsByDateThenSeq([...rows, newRow]);
    renderRows(updated);
    enableAddButtonIfValid();
  });

  // === 保存処理 ===
  const saveBtn = document.getElementById('saveBtn');
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      const rows = readCurrentRows();

      for (const r of rows) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(r.date)) { alert(`日付形式エラー: ${r.date}`); return; }
        const [yy, mm] = r.date.split('-').map(Number);
        if (yy !== Number(year) || mm !== Number(month)) { alert(`この月以外の日付が含まれています: ${r.date}`); return; }
        if (r.cost < 0 || !Number.isFinite(r.cost)) { alert(`金額が不正です: ${r.cost}`); return; }
        if (!r.trip) { alert(`Trip Type が未選択の日があります: ${r.date}`); return; }
      }

      saveBtn.disabled = true; saveBtn.textContent = '保存中…';

      try {
        const updates = rows.filter(r => r.id && initialIdSet.has(String(r.id)));
        for (const u of updates) {
          const resp = await fetch(`/api/expenses/${u.id}`, {
            method: 'PUT',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              station_from: u.from || null,
              station_to:   u.to   || null,
              note:         u.note || null,
              cost:         u.cost,
              trip_type:    u.trip,
              seq:          u.seq,
            }),
          });
          if (!resp.ok) {
            const t = await resp.text();
            throw new Error(`更新失敗 (ID:${u.id}): ${resp.status} ${t}`);
          }
        }

        const creates = rows.filter(r => !r.id);
        for (const c of creates) {
          const seq = Number.isFinite(c.seq) ? c.seq : 100;
          const resp = await fetch('/api/expenses', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              expense_report_id: reportId,
              expense_date:      c.date,
              seq:               seq,
              station_from:      c.from || null,
              station_to:        c.to   || null,
              note:              c.note || null,
              cost:              c.cost,
              trip_type:         c.trip,
              category:          'regular',
            }),
          });
          if (!resp.ok) {
            const t = await resp.text();
            throw new Error(`作成失敗 (Date:${c.date}): ${resp.status} ${t}`);
          }
        }

        alert('保存しました。');
        location.reload();

      } catch (err) {
        console.error(err);
        alert('保存でエラーが発生しました。\n' + (err?.message || err));
      } finally {
        saveBtn.disabled = false; saveBtn.textContent = '保存';
      }
    });
  }
});
</script>
@endpush
@endsection
