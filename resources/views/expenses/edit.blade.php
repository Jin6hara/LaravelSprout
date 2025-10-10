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

{{-- ▼ JSpreadsheet 初期化＋保存処理 --}}
@push('scripts')
<script>
// ▼ JSpreadsheet 初期化の直前に追加
let lastInsertAnchor = null; // 直前に右クリック等で選ばれていた行番号を記録

// 直近(上方向に遡って)Dateが入っている行を探すユーティリティ
function findNearestDateRow(instance, startRow) {
  const rows = instance.getData(false);
  // 1) まず上方向
  for (let i = startRow; i >= 0; i--) {
    if (rows[i] && rows[i][0]) return { idx: i, date: rows[i][0] };
  }
  // 2) 上で見つからなければ下方向
  for (let i = startRow + 1; i < rows.length; i++) {
    if (rows[i] && rows[i][0]) return { idx: i, date: rows[i][0] };
  }
  return null;
}

document.addEventListener('DOMContentLoaded', function () {
  if (!@json($hasReport)) return;

  const csrfToken   = @json(csrf_token());
  const reportId    = @json($report?->id);
  const year        = @json($y);
  const month       = @json($m);
  const initialRows = @json($rows);

  function enWeekday(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d)) return '';
    return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()];
  }

  const tripTypeOptions = [
    { id: 'round_trip', name: 'Round Trip' },
    { id: 'one_way',    name: 'One Way' },
  ];

  const matrix = initialRows.map(r => {
    const date = r.expense_date || '';
    return [
      date,                 // 0 Date (readOnly)
      enWeekday(date),      // 1 Day  (readOnly)
      r.station_from || '', // 2
      r.station_to   || '', // 3
      Number.isFinite(r.cost) ? r.cost : 0, // 4
      r.trip_type || '',    // 5
      r.note || '',         // 6
      r.id ?? '',           // 7 _id
      (r.seq ?? 100),       // 8 _seq
    ];
  });

  // ▼ 小関数：同日行を1つ下に追加
  function insertSiblingRowBelow(instance, rowIndex) {
    const d = instance.getValueFromCoords(0, rowIndex); // Date
    if (!d) { alert('この行には日付がありません。'); return; }
    const newRow = [ d, enWeekday(d), '', '', 0, '', '', '', 100 ];
    instance.insertRow(1, rowIndex + 1, false);
    instance.setRowData(rowIndex + 1, newRow);
    instance.setSelectedRows([rowIndex + 1]);
    instance.openEditor(4, rowIndex + 1); // col=4 Amount
  }

  // ▼ 小関数：指定日での挿入（同日の最後の明細の直下 or 日付順）
  function insertByDate(instance, dateStr) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) { alert('有効な日付を選択してください'); return; }
    const [yy, mm] = dateStr.split('-').map(Number);
    if (yy !== Number(year) || mm !== Number(month)) { alert('対象月内の日付を選択してください'); return; }

    const rows = instance.getData(false);
    let lastIdx = -1;
    rows.forEach((r, i) => { if (r[0] === dateStr) lastIdx = i; });

    if (lastIdx < 0) {
      let insertPos = rows.length; // 末尾
      for (let i = 0; i < rows.length; i++) {
        const cur = rows[i][0];
        if (cur && cur < dateStr) insertPos = i + 1;
      }
      const newRow = [dateStr, enWeekday(dateStr), '', '', 0, '', '', '', 100];
      instance.insertRow(1, insertPos, false);
      instance.setRowData(insertPos, newRow);
      instance.setSelectedRows([insertPos]);
      instance.openEditor(4, insertPos);
      return;
    }

    insertSiblingRowBelow(instance, lastIdx);
  }

  // ▼ JSpreadsheet 初期化
  const sheet = jspreadsheet(document.getElementById('sheet'), {
    worksheets: [{
      data: matrix,
      columns: [
        { title:'Date', type:'text', width:120, readOnly:true }, // 0
        { title:'Day',  type:'text', width:70,  readOnly:true }, // 1
        { title:'From', type:'text', width:160 },
        { title:'To',   type:'text', width:160 },
        { title:'Amount (JPY)', type:'numeric', width:130, mask:'#,##0' },
        { title:'Trip Type', type:'dropdown', width:140, source: tripTypeOptions },
        { title:'Note', type:'text', width:220 },
        { title:'_id',  type:'text', visible:false },
        { title:'_seq', type:'numeric', visible:false },
      ],
      minDimensions: [9, Math.max(matrix.length, 1)],

      // 右クリックメニューを拡張（既定の Insert を残す）
      contextMenu: (obj, x, y, e) => {
        const items = [];
        if (y >= 0) {
          items.push({
            title: 'この日の下に行を追加（Date/Day自動）',
            onclick: () => {
              lastInsertAnchor = y;
              obj.insertRow(1, y + 1, false);
            },
          });
          items.push({ type:'line' });
        }

        // 既定メニューのフォールバック
        const base = (typeof jspreadsheet?.contextMenu === 'function')
          ? jspreadsheet.contextMenu(obj, x, y, e)
          : [
              { title: 'Copy',  shortcut: 'Ctrl + C', onclick: () => obj.copy(true) },
              { title: 'Paste', shortcut: 'Ctrl + V', onclick: () => obj.paste(true) },
              { title: 'Undo',  shortcut: 'Ctrl + Z', onclick: () => obj.undo() },
              { title: 'Redo',  shortcut: 'Ctrl + Y', onclick: () => obj.redo() },
            ];
        return items.concat(base);
      },

      // 行挿入直前：アンカー（基準行）を安全に記録
      onbeforeinsertrow: (instance, rowNumber, numOfRows, insertBefore, history) => {
        const sel = instance.getSelectedRows();
        if (sel && sel.length) {
          lastInsertAnchor = sel[0];
        } else if (typeof rowNumber === 'number') {
          const idx = rowNumber + (insertBefore ? 0 : -1);
          lastInsertAnchor = Math.max(0, idx);
        } else {
          lastInsertAnchor = 0;
        }
      },

      // 行挿入後：Date/Day/_seq を自動補完
      oninsertrow: (instance, rowNumber, numRows, history) => {
        const anchor = (lastInsertAnchor ?? (rowNumber > 0 ? rowNumber - 1 : rowNumber));
        const base = findNearestDateRow(instance, Math.max(0, anchor));
        for (let i = 0; i < numRows; i++) {
          const r = rowNumber + i;
          let dateToUse = base?.date || '';
          if (!dateToUse) {
            const alt = findNearestDateRow(instance, r);
            if (alt) dateToUse = alt.date;
          }
          if (dateToUse) {
            instance.setValueFromCoords(0, r, dateToUse);            // Date
            instance.setValueFromCoords(1, r, enWeekday(dateToUse)); // Day
          }
          instance.setValueFromCoords(8, r, 100);                    // _seq 既定
        }
        lastInsertAnchor = null;
        instance.openEditor(4, rowNumber); // 使い勝手: 金額セルを開く
      },
    }]
  });

  // ▼ 「指定日を追加」ボタンの実装
  const addByDateBtn = document.getElementById('addByDateBtn');
  const pickDate     = document.getElementById('pickDate');
  if (pickDate && addByDateBtn) {
    pickDate.addEventListener('change', () => {
      addByDateBtn.disabled = !pickDate.value;
    });
    addByDateBtn.disabled = !pickDate.value;
    addByDateBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const v = pickDate.value;
      if (!v) return;
      insertByDate(sheet[0], v);
    });
  }

  // ▼ 保存処理（既存ロジックを維持）
  function readCurrentRows() {
    const data = sheet[0].getData(false);
    return data.map(arr => ({
      date:  arr[0] || '',
      day:   arr[1] || '',
      from:  arr[2] || '',
      to:    arr[3] || '',
      cost:  (arr[4] === '' || arr[4] == null) ? 0 : Number(String(arr[4]).replace(/,/g,'')),
      trip:  arr[5] || '',
      note:  arr[6] || '',
      id:    arr[7] || '',
      seq:   (arr[8] === '' || arr[8] == null) ? 100 : Number(arr[8]),
    })).filter(r => r.date);
  }

  const initialIdSet = new Set(initialRows.map(r => String(r.id)));
  const saveBtn = document.getElementById('saveBtn');

  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      const rows = readCurrentRows();

      // バリデーション
      for (const r of rows) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(r.date)) { alert(`日付形式エラー: ${r.date}`); return; }
        const [yy, mm] = r.date.split('-').map(Number);
        if (yy !== Number(year) || mm !== Number(month)) { alert(`この月以外の日付が含まれています: ${r.date}`); return; }
        if (r.cost < 0 || !Number.isFinite(r.cost)) { alert(`金額が不正です: ${r.cost}`); return; }
        if (!r.trip) { alert(`Trip Type が未選択の日があります: ${r.date}`); return; }
      }

      saveBtn.disabled = true; saveBtn.textContent = '保存中…';

      try {
        // UPDATE
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

        // CREATE
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

// ========== ここから：指定日追加（rules 1-4 対応） ==========
(function(){
  if (!@json($hasReport)) return;

  const addBtn   = document.getElementById('addByDateBtn');
  const datePick = document.getElementById('pickDate');

  // 入力があれば有効化
  function toggleAddBtn(){
    addBtn.disabled = !/^\d{4}-\d{2}-\d{2}$/.test(datePick?.value || '');
  }
  datePick?.addEventListener('input', toggleAddBtn);
  toggleAddBtn();

  // 'YYYY-MM-DD' → epoch（比較用）
  function toEpoch(d){
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(d);
    if(!m) return NaN;
    const y=+m[1], mo=+m[2]-1, da=+m[3];
    return new Date(y, mo, da).getTime();
  }

  // 英語曜日（既存の enWeekday と同じ仕様をここでも使う）
  function weekdayEn(dateStr){
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d)) return '';
    return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()];
  }

  // 現在の行データ（内部列含むマトリクス）を取得
  function getMatrix(){
    return sheet[0].getData(false);
    // 列: [0]Date [1]Day [2]From [3]To [4]Amount [5]TripType(id) [6]Note [7]_id [8]_seq
  }

  // ルール④：日付→seq（昇順）で並び替えして全体を再描画
  function sortAndRedraw(matrix){
    matrix.sort((a,b)=>{
      const ad = toEpoch(a[0]||''); // Date
      const bd = toEpoch(b[0]||'');
      if (ad !== bd) return ad - bd;                     // 日付昇順
      const as = Number(a[8] ?? 0), bs = Number(b[8] ?? 0);
      return as - bs;                                    // 同日内は seq 昇順
    });
    sheet[0].setData(matrix);
  }

  // ルール③：seq を決める
  //   ・同日既存が 0 件 → 100
  //   ・同日既存が 1 件（=最大）→ 既存max + 1024
  //   ・同日既存が 2 件以上 → 既存max + 1024（末尾追加）
  //
  //   ※「間に挿入して中間値」にも対応できるよう、将来は選択行の直後に入れる設計に拡張可。
  function nextSeqForDate(matrix, dateStr){
    const same = matrix.filter(r => r[0] === dateStr);
    if (same.length === 0) return 100;
    const maxSeq = same.reduce((m,r)=>Math.max(m, Number(r[8] ?? 0)), -Infinity);
    return (Number.isFinite(maxSeq) ? maxSeq : 100) + 1024;
  }

  // 行を1つ作って返す
  function makeRow(dateStr, seq){
    return [
      dateStr,             // Date（ルール①：即反映・編集不可）※列定義で readOnly
      weekdayEn(dateStr),  // Day  （ルール①：即反映・編集不可）※列定義で readOnly
      '',                  // From
      '',                  // To
      0,                   // Amount
      '',                  // TripType(id)
      '',                  // Note
      '',                  // _id（新規）
      seq,                 // _seq
    ];
  }

  // クリックで追加
  addBtn?.addEventListener('click', (e)=>{
    e.preventDefault();
    const d = datePick?.value || '';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) { alert('日付を選択してください'); return; }

    // ルール②：同じ操作を何度でも実行可（複数OK）
    const matrix = getMatrix();

    // ルール③：seq 決定（同日最大なら +1024、初回は 100）
    const seq = nextSeqForDate(matrix, d);

    // 1行生成
    const row = makeRow(d, seq);

    // 追加 → ルール④で日付→seqに並べ替えて再描画
    matrix.push(row);
    sortAndRedraw(matrix);

    // UX：直後に TripType（ドロップダウン）へフォーカス
    // 追加された行のインデックスを再取得してからフォーカスを当てる
    const data = sheet[0].getData(false);
    const idx = data.findIndex(r => r[0]===d && Number(r[8])===seq);
    if (idx >= 0) {
      // (rowIndex, colIndex)
      sheet[0].setSelectedCells([[idx, 5, idx, 5]]);
      sheet[0].openEditor(idx, 5);
    }
  });
})();
</script>
@endpush

@endsection