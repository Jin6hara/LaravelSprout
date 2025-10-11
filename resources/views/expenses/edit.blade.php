{{-- resources/views/expenses/edit.blade.php --}}
@extends('layouts.app')

@section('title', '交通費（表示のみ）')

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
  <style>
    .page-wrap { max-width: 1300px; margin: 20px auto; }
    .header-box {
      background: #f8f9fa; padding: 12px 16px; border-radius: 8px; border: 1px solid #78a3faff;
    }
    .header-box .meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 14px; }
    .total { font-weight: 700; }
    .muted { color: #6b7280; }
    #sheet { width: 100%; max-width: 100%; height: auto; margin: 0 auto; }

    /* ▼ 文字サイズ */
    .header-box .meta > div {
      font-size: 1rem;      /* 通常より大きめ（約17.6px） */
      line-height: 1.5;       /* 行間を少し広く */
    }

    .header-box .meta strong {
      font-size: 1rem;     /* 強調文字はさらに少し大きく */
      font-weight: 600;
    }

    /* 定期券ブロック内の細文字（日付）を落ち着かせる */
    .header-box .meta .muted {
      font-size: 0.95rem;
      color: #6b7280;
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
            <br>If you need to correct, please contact Native HR at 06-6352-0650.
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

{{-- ▼ JSpreadsheet 初期化＋保存／指定日追加（seq計算＆並び替え） --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!@json($hasReport)) return; // レポート無い月は何もしない

  const csrfToken     = @json(csrf_token());
  const reportId      = @json($report?->id);
  const year          = @json($y);
  const month         = @json($m);
  const initialRows   = @json($rows);
  const eventOnMap    = @json($eventOnMap ?? []);
  const passActiveMap = @json($passActiveMap ?? []); // ★ 定期券

  // 色定数（6桁HEXで安定化）
  const COLOR_WORK_ON  = '#6b92ff';  // Work=ON（イベントON）
  const COLOR_WORK_OFF = '#e68484';  // Work=OFF
  const COLOR_PASS_ON  = '#9bf59b';  // Pass=有効期間
  const COLOR_PASS_OFF = '#ffffff';  // Pass=無効

  // ★ 操作ボタンHTML
  const ACTION_BTN_HTML = '<button type="button" class="btn btn-outline-danger btn-sm js-row-del" title="この行を削除">Del</button>';
  const ADD_BTN_HTML    = '<button type="button" class="btn btn-outline-primary btn-sm js-row-add" title="この日の行を下に追加">Add</button>';
  // 列定数
  const COL = Object.freeze({
    ACTIONS: 0,   // 削除ボタン
    ADD:     1,   // 追加ボタン
    DATE:    2,
    DAY:     3,
    WORK:    4,
    FROM:    5,
    TO:      6,
    AMOUNT:  7,
    TRIP:    8,
    NOTE:    9,
    ID:      10,  // hidden
    SEQ:     11,  // hidden
    PASS:    12,
  });

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
  // 列: Date(0) / Day(1) / Work(2:color) / From(3) / To(4) / Amount(5) / Trip(6) / Note(7) / _id(8) / _seq(9) / Pass(10:color)
  const matrix = initialRows.map(r => {
    const date = r.expense_date || '';

    const isEventOn = !!eventOnMap[date];
    const isPassOn  = !!passActiveMap[date];

    const colorWork = isEventOn ? COLOR_WORK_ON : COLOR_WORK_OFF;
    const colorPass = isPassOn  ? COLOR_PASS_ON : COLOR_PASS_OFF;

    return [
      ACTION_BTN_HTML,        // 0: ★ Delete button
      ADD_BTN_HTML,           // 1
      date,
      enWeekday(date),
      colorWork,             // 2: Work (Event)
      r.station_from || '',  // 3
      r.station_to   || '',  // 4
      Number.isFinite(r.cost) ? r.cost : 0, // 5
      r.trip_type || '',     // 6
      r.note || '',          // 7
      r.id ?? '',            // 8
      (r.seq ?? 100),        // 9
      colorPass,             // 10: Pass
    ];
  });

  // === シート生成 ===
  const sheet = jspreadsheet(document.getElementById('sheet'), {
    worksheets: [
      {
        data: matrix,
        columns: [
          { title:'-',              type:'html',    width:50,  readOnly:true }, // 0 ★ Actions（先頭）
          { title:'+',              type:'html',    width:50,  readOnly:true }, // 1: 追加
          { title:'Date',           type:'text',     width:110, readOnly:true                  }, // 0
          { title:'Day',            type:'text',     width:65,  readOnly:true                  }, // 1
          { title:'Work',           type:'color',    width:65,  render:'square', readOnly:true }, // 2
          { title:'From',           type:'text',     width:200                                 }, // 3
          { title:'To',             type:'text',     width:200                                 }, // 4
          { title:'Amount',   type:'numeric',  width:100, mask:'#,##0'                   }, // 5
          { title:'Trip Type',      type:'dropdown', width:100, source: tripTypeOptions        }, // 6
          { title:'Note',           type:'text',     width:240                                 }, // 7
          { title:'_id',            type:'text',     width:0,   readOnly:true                  }, // 8
          { title:'_seq',           type:'numeric',  width:0,   readOnly:true                  }, // 9
          { title:'Pass',           type:'color',    width:65,  render:'square', readOnly:true }, // 10
        ],
        minDimensions: [13, Math.max(matrix.length, 1)],
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

        //updateTable: here

        // 選択行の記憶用（挿入位置のヒントに使う）
        onselection: function(el, column, row) {
          lastSelectedRow = (typeof row === 'number') ? row : null;
        }
      }
    ]
  });

// 隠し列処理
function hideInternalCols() {
  sheet[0].hideColumn(COL.ID);
  sheet[0].hideColumn(COL.SEQ);
}
hideInternalCols();

  // 便利関数：現在の全行データをオブジェクト配列に
  function readCurrentRows() {
    const data = sheet[0].getData(false);
    return data.map(arr => ({
      date:      arr[COL.DATE] || '',
      day:       arr[COL.DAY]  || '',
      colorWork: arr[COL.WORK] || COLOR_WORK_OFF,
      from:      arr[COL.FROM] || '',
      to:        arr[COL.TO]   || '',
      cost:      (arr[COL.AMOUNT] === '' || arr[COL.AMOUNT] == null) ? 0 : Number(String(arr[COL.AMOUNT]).replace(/,/g,'')),
      trip:      arr[COL.TRIP] || '',
      note:      arr[COL.NOTE] || '',
      id:        arr[COL.ID]   || '',
      seq:       (arr[COL.SEQ] === '' || arr[COL.SEQ] == null) ? 100 : Number(arr[COL.SEQ]),
      colorPass: arr[COL.PASS] || COLOR_PASS_OFF,
    })).filter(r => r.date);
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
    const newMatrix = rows.map(r => {
      const work = eventOnMap[r.date]    ? COLOR_WORK_ON : COLOR_WORK_OFF;
      const pass = passActiveMap[r.date] ? COLOR_PASS_ON : COLOR_PASS_OFF;
      return [
        ACTION_BTN_HTML,      // 0
        ADD_BTN_HTML,         // 1: 追加
        r.date,
        enWeekday(r.date),
        work,              // 2
        r.from || '',      // 3
        r.to   || '',      // 4
        r.cost || 0,       // 5
        r.trip || '',      // 6
        r.note || '',      // 7
        r.id   || '',      // 8
        r.seq  ?? 100,     // 9
        pass,              // 10
      ];
    });
    sheet[0].setData(newMatrix);
  }

  // 既存IDの集合（更新判定用）
  const initialIdSet = new Set(initialRows.map(r => String(r.id)));

  // === 指定日追加ロジック ===
  const pickDateEl   = document.getElementById('pickDate');
  const addByDateBtn = document.getElementById('addByDateBtn');
  let lastSelectedRow = null; // 直近に選択した行番号（挿入位置のヒント）

  // 日付入力の妥当性でボタン制御
  function isValidDateStr(yyyy_mm_dd) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(yyyy_mm_dd)) return false;
    const [yy, mm, dd] = yyyy_mm_dd.split('-').map(Number);
    const d = new Date(yyyy_mm_dd + 'T00:00:00');
    if (isNaN(d)) return false;
    // 入力月が画面の対象年/月と一致必須
    return (yy === Number(year) && mm === Number(month) && dd >= 1 && dd <= 31);
  }

  function enableAddButtonIfValid() {
    const v = pickDateEl?.value || '';
    addByDateBtn.disabled = !isValidDateStr(v);
  }
  pickDateEl?.addEventListener('input', enableAddButtonIfValid);
  enableAddButtonIfValid();

  // date に対する seq の決定
  // ルール:
  // 1) 同日の最大 seq が分かる場合、基本は「最大 + 1024」で末尾追加
  // 2) ただし、同日内で「選択行の直後」に入れたい場合は、その選択行の seq と
  //    次の（同日かつ seq が大きい）行の seq の中間値を取る
  // 3) 中間値が取れない（隙間がない）場合は安全に「最大 + 1024」
  function decideSeqForDate(targetDate, rows, hintAfterSeq = null) {
    const same = rows.filter(r => r.date === targetDate).sort((a,b)=>a.seq-b.seq);
    if (same.length === 0) return 1024; // 同日の先頭として 1024

    const maxSeq = same[same.length - 1].seq ?? 100;
    if (hintAfterSeq == null) {
      return maxSeq + 1024;
    }

    // hintAfterSeq より大きい最小 seq（= 直後の行の seq）を探す
    const next = same.find(r => r.seq > hintAfterSeq);
    if (next && (next.seq - hintAfterSeq) > 1) {
      return Math.floor((hintAfterSeq + next.seq) / 2);
    }
    // 中間値が取れない場合は末尾へ
    return maxSeq + 1024;
  }

  // 追加ボタン
  addByDateBtn?.addEventListener('click', () => {
    const dateStr = pickDateEl?.value || '';
    if (!isValidDateStr(dateStr)) {
      alert('この月内の日付を選択してください。');
      return;
    }

    // 現在の行を取得
    const rows = readCurrentRows();

    // 「選択行が同日なら、その直後に挿入」を試みる
    let hintAfterSeq = null;
    if (lastSelectedRow != null) {
      const selected = rows[lastSelectedRow];
      if (selected && selected.date === dateStr) {
        hintAfterSeq = selected.seq ?? null;
      }
    }

    const newSeq = decideSeqForDate(dateStr, rows, hintAfterSeq);

    // 新規行データ（Date/Day は固定値、編集不可。Trip/Note等は空でOK）
    const newRow = {
      date: dateStr,
      day:  enWeekday(dateStr),
      from: '',
      to:   '',
      cost: 0,
      trip: '',
      note: '',
      id:   '',    // 新規なので空
      seq:  newSeq
    };

    // rowsに追加して、ルール4: 日付→seq で再ソート → 反映
    const updated = sortRowsByDateThenSeq([...rows, newRow]);
    renderRows(updated);

    // 連続入力をしやすくする（複数日追加OK）
    // 次の追加で再び検証されるので、日付は保持のままでもOK
    enableAddButtonIfValid();
  });

  // === 保存処理 ===
  const saveBtn = document.getElementById('saveBtn');
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      const rows = readCurrentRows();

      // バリデーション（この月内のデータか）
      for (const r of rows) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(r.date)) {
          alert(`日付形式エラー: ${r.date}`); return;
        }
        const [yy, mm] = r.date.split('-').map(Number);
        if (yy !== Number(year) || mm !== Number(month)) {
          alert(`この月以外の日付が含まれています: ${r.date}`); return;
        }
        if (r.cost < 0 || !Number.isFinite(r.cost)) {
          alert(`金額が不正です: ${r.cost}`); return;
        }
        if (!r.trip) {
          alert(`Trip Type が未選択の日があります: ${r.date}`); return;
        }
      }

      saveBtn.disabled = true; saveBtn.textContent = '保存中…';

      try {
        // 1) UPDATE: id がある行
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
              trip_type:    u.trip,   // 'round_trip' | 'one_way'
              seq:          u.seq,
              // category は送らない（保持）
            }),
          });
          if (!resp.ok) {
            const t = await resp.text();
            throw new Error(`更新失敗 (ID:${u.id}): ${resp.status} ${t}`);
          }
        }

        // 2) CREATE: id が無い行
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
              trip_type:         c.trip,      // 'round_trip' | 'one_way'
              category:          'regular',   // Category列削除のため既定 regular
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
    // クリック委譲（削除と追加の両方を処理）
  const sheetEl = document.getElementById('sheet');
  sheetEl.addEventListener('click', async (e) => {
    const delBtn = e.target.closest('.js-row-del');
    const addBtn = e.target.closest('.js-row-add');
    const td = e.target.closest('td');
    if (!td) return;
    const rowIndex = Number(td.getAttribute('data-y'));
    if (Number.isNaN(rowIndex) || rowIndex < 0) return;

    // --- 削除 ---
    if (delBtn) {
      const rowData = sheet[0].getRowData(rowIndex);
      const id = rowData[COL.ID]; // _id
      if (!id) { sheet[0].deleteRow(rowIndex); return; }
      if (!confirm('この行を削除します。よろしいですか？')) return;
      try {
        const resp = await fetch(`/api/expenses/${id}`, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        if (!resp.ok) {
          const t = await resp.text();
          throw new Error(`削除失敗 (ID:${id}): ${resp.status} ${t}`);
        }
        sheet[0].deleteRow(rowIndex);
      } catch (err) {
        console.error(err);
        alert('削除エラー: ' + (err?.message || err));
      }
      return;
    }

  // --- 追加（クリック行の「下」に同日行を1つ追加） ---
    if (addBtn) {
      const rows   = readCurrentRows();
      const rowArr = sheet[0].getRowData(rowIndex);
      const date   = rowArr[COL.DATE];
      const curSeq = Number(rowArr[COL.SEQ] ?? 100);

      // 同日行を seq 昇順で
      const same = rows.filter(r => r.date === date).sort((a,b) => a.seq - b.seq);
      const maxSeq = same.length ? same[same.length - 1].seq : 100;

      let newSeq;
      // ① 同日の行が1つだけ or クリック行が最大 → +1024
      if (same.length === 1 || curSeq === maxSeq) {
        newSeq = maxSeq + 1024;
      } else {
        // ② 次に大きい seq を探して中間値
        const next = same.find(r => r.seq > curSeq);
        if (next && (next.seq - curSeq) > 1) {
          newSeq = Math.floor((curSeq + next.seq) / 2);
        } else {
          // 隙間がない時は最大 +1024
          newSeq = maxSeq + 1024;
        }
      }

      // 新規行（同日、下に入るよう seq を調整済み）
      const newRow = {
        date,
        day:  enWeekday(date),
        from: '',
        to:   '',
        cost: 0,
        trip: '',
        note: '',
        id:   '',
        seq:  newSeq,
      };

      // クリック行の「下」に一旦挿入してから、日付→seq で再ソート反映
      const rowsWithNew = [
        ...rows.slice(0, rowIndex + 1),
        newRow,
        ...rows.slice(rowIndex + 1),
      ];

      const updated = rowsWithNew.slice().sort((a, b) => {
        if (a.date < b.date) return -1;
        if (a.date > b.date) return 1;
        return a.seq - b.seq;
      });

      renderRows(updated);

      // 任意：新規行を選択状態にしたい場合（見失わないように）
      const newIndex = updated.findIndex(r => r.date === date && r.seq === newSeq);
      if (newIndex >= 0) {
        sheet[0].selectCell(COL.FROM, newIndex); // 例えば From にフォーカス
      }
    }
  });
});
</script>
@endpush
@endsection
