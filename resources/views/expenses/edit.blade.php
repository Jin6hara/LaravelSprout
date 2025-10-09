@extends('layouts.app')

@section('title', '交通費（表示のみ）')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/tabulator-tables@6.2.5/dist/css/tabulator.min.css">
<style>
  .page-wrap { max-width: 1100px; margin: 20px auto; }
  .header-box {
    background: #f8f9fa; padding: 12px 16px; border-radius: 8px; border: 1px solid #78a3faff;
  }
  .header-box .meta { display:flex; flex-wrap:wrap; gap:16px; font-size:14px; }
  .total { font-weight:700; }
  .row-actions { display:flex; gap:6px; justify-content:center; }
  .row-actions button { font-size:12px; padding:2px 6px; }
  .muted { color:#6b7280; }
  /* ON/OFF 行の視覚化 */
  .fc-row-on   { background-color: #ecfdf5 !important; }
  .fc-row-off  { background-color: #f3f4f6 !important; color:#6b7280; }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css">
@endpush

@section('content')
<div class="page-wrap">
  <h1 class="mb-3">交通費（{{ $y }}年{{ $m }}月）</h1>

  {{-- ▼ 月選択フォーム（GET） --}}
  <form method="GET" class="mb-3 d-flex align-items-center gap-2" id="monthForm">
    <label for="monthPick" class="form-label m-0">対象月</label>
    <input
      type="month"
      id="monthPick"
      name="monthpick"
      class="form-control form-control-sm"
      style="width:170px"
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
          <button id="addByDateBtn" class="btn btn-success btn-sm">＋指定日を追加</button>
          <button id="saveBtn" class="btn btn-primary btn-sm">保存</button>
          <div class="ms-auto"></div>
          <form
            method="POST"
            action="{{ route('expenses.submit', ['report' => $report->id]) }}"
            onsubmit="return confirm('この月の交通費を提出しますか？（提出後は編集できません）');">
            @csrf
            @method('PUT')
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

  <div id="expensesTable"></div>
</div>

{{-- 対象月検索 --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  const monthInput = document.getElementById('monthPick');
  const btn        = document.getElementById('monthSearchBtn');

  function doSearch() {
    const v = monthInput?.value || '';
    if (!/^\d{4}-\d{2}$/.test(v)) {
      alert('対象月を選択してください。');
      return;
    }
    const [yy, mm] = v.split('-');
    const url = new URL(window.location.href);
    url.searchParams.set('year', yy);
    url.searchParams.set('month', Number(mm));
    window.location = url.toString();
  }

  btn?.addEventListener('click', (e) => { e.preventDefault(); doSearch(); });
  monthInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doSearch(); }});
});
</script>

{{-- Tabulator 本体 --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ===== Blade から受け取る前提データ =====
  const eventOnMap = @json($eventOnMap ?? []);
  const initialRows = @json($rows);
  const isLocked = @json(($report->submitted_at ?? null) !== null);
  const canEdit  = !isLocked;
  const reportId = @json($report->id ?? null);
  const csrf     = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
  const year     = Number(@json($y));
  const month    = Number(@json($m));

  // ===== Utils =====
  const weekdayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

  function toYMDLocal(v){
    if (!v) return v;
    if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v)) return v;
    const d = new Date(v);
    if (isNaN(d)) return String(v).slice(0, 10);
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const day = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${day}`;
  }
  function makeSortKey(ymd, seq){
    const d = toYMDLocal(ymd) || '0000-00-00';
    const s = String(Number(seq ?? 0)).padStart(9,'0'); // 0埋めで安定
    return `${d}-${s}`;
  }
  function normalizeRow(r){
    const ymd = toYMDLocal(r.expense_date);
    const sq  = Number.isFinite(Number(r.seq)) ? Number(r.seq) : 0;
    return {
      ...r,
      expense_date: ymd,
      seq: sq,
      cost: Number(r.cost ?? 0),
      _sort: makeSortKey(ymd, sq),   // ★ ソート専用キー
    };
  }
  function fmtInt(n){ return (n ?? 0).toLocaleString(); }
  function recalcFooterSum(){
    const rows = table.getData();
    const sum = rows.reduce((acc, r)=>acc + Number(r.cost ?? 0), 0);
    const el = document.getElementById('sumCost');
    if (el) el.innerText = fmtInt(sum);
  }
  function isOnDay(ymd){ return !!eventOnMap[ymd]; }
  function recalcFooterSum(){
  const rows = table.getData();
  let sum = 0;
  for (const r of rows) {
    // "1,234" や " 500 " にも耐性
    const raw = r?.cost ?? 0;
    const n = typeof raw === 'number'
      ? raw
      : parseInt(String(raw).replace(/[^\d\-]/g, ''), 10) || 0;
    sum += n;
  }
  const el = document.getElementById('sumCost');
  if (el) el.innerText = (sum).toLocaleString();
  }

  // ===== API helpers =====
  async function apiCreate(payload){
    const res = await fetch(@json(route('api.expenses.store')), {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
      body: JSON.stringify(payload),
    });
    if(!res.ok) throw await res.json().catch(()=>({message:'Create failed'}));
    return res.json();
  }
  async function apiUpdate(id, payload){
    const url = @json(route('api.expenses.update', ['expense'=>'__ID__'])).replace('__ID__', id);
    const res = await fetch(url, {
      method:'PUT',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
      body: JSON.stringify(payload),
    });
    if(!res.ok) throw await res.json().catch(()=>({message:'Update failed'}));
    return res.json();
  }
  async function apiDelete(id){
    const url = @json(route('api.expenses.destroy', ['expense'=>'__ID__'])).replace('__ID__', id);
    const res = await fetch(url, { method:'DELETE', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'} });
    if(!res.ok) throw await res.json().catch(()=>({message:'Delete failed'}));
    return res.json();
  }

  // ===== seq 補助 =====
  function sameDaySeqs(ymd){
    const rows = table.getData();
    const seqs = [];
    for(const r of rows){
      if (toYMDLocal(r.expense_date) === ymd) seqs.push(Number(r.seq ?? 0));
    }
    seqs.sort((a,b)=>a-b);
    return seqs;
  }
  function maxSeqForDate(ymd){
    return sameDaySeqs(ymd).slice(-1)[0] ?? 0;
  }

  // ===== 固定ソート（_sort 1本化） =====
  function forceSortKey(){
    if (typeof table.clearSort === 'function') table.clearSort();
    table.setSort([{ column: '_sort', dir: 'asc' }]); // ← _sort だけ
    if (typeof table.redraw === 'function') table.redraw(true); // 再描画
  }

  // ===== Columns =====
  const columns = [
    // 行アクション
    {
      title:'', field:'_actions', width:80, hozAlign:'center', headerSort:false,
      formatter: () => `<div class="row-actions"><button data-act="add">＋</button><button data-act="del">ー</button></div>`,
      cellClick: function(e, cell){
        const btn = e.target.closest('button');
        if (!btn || isLocked) return;

        const act = btn.getAttribute('data-act');
        const row = cell.getRow();
        const data = row.getData();

        if (act === 'add') {
          const d = toYMDLocal(data.expense_date);
          const curSeq = Number(data.seq ?? 0);
          const seqs = sameDaySeqs(d);
          const maxSeq = seqs.length ? seqs[seqs.length - 1] : curSeq;

          let newSeq;
          if (seqs.length <= 1 || curSeq === maxSeq) {
            newSeq = curSeq + 1024;
          } else {
            const nextGreater = seqs.find(s => s > curSeq);
            if (nextGreater == null) newSeq = curSeq + 1024;
            else {
              const diff = nextGreater - curSeq;
              newSeq = curSeq + Math.floor(diff / 2);
              if (newSeq <= curSeq) newSeq = curSeq + 1;
            }
          }

          const payload = {
            expense_report_id: reportId,
            expense_date: d,
            seq: newSeq,
            station_from: null, station_to: null, note: null,
            cost: 0, trip_type: 'round_trip', category: 'regular', commuter_pass_id: null,
          };
          apiCreate(payload).then(created=>{
            table.addRow(normalizeRow(created));  // _sort 付きで投入
            forceSortKey();
            recalcFooterSum();
          }).catch(err=>alert(err?.message || 'Add failed'));
        }

        if (act === 'del') {
          if (!data.id) { row.delete(); forceSortKey(); return; }
          if (!confirm('この行を削除しますか？')) return;
          apiDelete(data.id).then(()=>{
            row.delete();
            forceSortKey();
            recalcFooterSum();
          }).catch(err=>alert(err?.message || 'Delete failed'));
        }
      }
    },

    // 表示カラム
    { title:'日付', field:'expense_date', width:120, headerSort:true, editor:false,
      sorter:(a,b,rowA,rowB) => {
        const A = toYMDLocal(rowA.getData().expense_date);
        const B = toYMDLocal(rowB.getData().expense_date);
        if (A===B) return 0; return A < B ? -1 : 1;
      },
      formatter: cell => toYMDLocal(cell.getValue()),
    },
    { title:'Weekday', field:'weekday', width:100, headerSort:false, editor:false,
      formatter: cell => {
        const d = toYMDLocal(cell.getRow().getData().expense_date);
        if (!d) return '';
        return weekdayNames[new Date(d+'T00:00:00').getDay()] ?? '';
      }
    },
    { title:'From', field:'station_from', width:140, headerSort:false, editor: canEdit ? 'input' : false },
    { title:'To', field:'station_to', width:140, headerSort:false, editor: canEdit ? 'input' : false },
    { title:'Amount', field:'cost', width:110, hozAlign:'right', headerSort:false,
      editor: canEdit ? 'number' : false,
      mutatorEdit:(v)=> (v===''||v==null)?0:(parseInt(v,10)||0),
      formatter:(cell)=> {
        const v = cell.getValue();
        return (v==null)?'':Number(v).toLocaleString();
      },
    },
    { title:'Type', field:'trip_type', width:120, headerSort:false,
      editor: canEdit ? 'list' : false,
      editorParams:{ values:{'round_trip':'round_trip','one_way':'one_way'} }
    },
    { title:'！', field:'category', width:110, headerSort:false,
      editor: canEdit ? 'list' : false,
      editorParams:{ values:{'regular':'regular','irregular':'irregular'} }
    },
    { title:'Note', field:'note', headerSort:false, editor: canEdit ? 'input' : false, minWidth:200, widthGrow:1 },

    // 非表示の内部カラム
    { title:'seq',   field:'seq',   visible:false, sorter:'number', headerSort:false },
    { title:'_sort', field:'_sort', visible:false, headerSort:false }, // ★ これで並べる
    { title:'id',    field:'id',    visible:false },
  ];

  // ===== Tabulator 初期化 =====
  const table = new Tabulator("#expensesTable", {
    selectableRange: true,
    selectableRangeColumns: true,
    selectableRangeRows: true,
    clipboardCopyRowRange:"range", //change default selector to selected
    data: initialRows.map(normalizeRow),
    columns,
    layout: "fitColumns",
    height: "600px",
    reactiveData: true,
    selectable: 1,
    initialSort: [{column:'_sort', dir:'asc'}],
    rowFormatter:function(row){
      const d = row.getData().expense_date;
      if (!d) return;
      const el = row.getElement();
      el.classList.remove('fc-row-on','fc-row-off');
      if (isOnDay(d)) el.classList.add('fc-row-on'); else el.classList.add('fc-row-off');
    },
    cellEdited:function(cell){
      const row  = cell.getRow();
      const data = row.getData();
      if (data?.id) dirtyIds.add(data.id);
      if (cell.getField()==='cost') recalcFooterSum();

      // ★ 日付 or seq が変わったら _sort を更新して再ソート
      if (cell.getField()==='expense_date' || cell.getField()==='seq') {
        row.update({ _sort: makeSortKey(data.expense_date, data.seq) });
        forceSortKey();
      }
    },
  });

  table.on('tableBuilt',    recalcFooterSum);
  table.on('dataProcessed', recalcFooterSum);

  // 初回 & データ処理後にも固定
  table.on('tableBuilt',  forceSortKey);
  table.on('dataProcessed', forceSortKey);

  // 変更追跡 & 保存
  const dirtyIds = new Set();
  document.getElementById('saveBtn')?.addEventListener('click', async () => {
    try {
      const active = document.activeElement;
      const tableEl = document.getElementById('expensesTable');
      if (active && tableEl && tableEl.contains(active)) active.blur();
      if (typeof table.cancelEdit === 'function') table.cancelEdit();

      if (typeof table.getEditedCells === 'function') {
        const editedCells = table.getEditedCells();
        editedCells.forEach(c => {
          const rid = c.getRow().getData()?.id;
          if (rid) dirtyIds.add(rid);
        });
      }

      const all = table.getData();
      const tx = [];
      for (const id of dirtyIds) {
        const r = all.find(x => x.id === id);
        if (!r) continue;
        const payload = {
          station_from: r.station_from ?? null,
          station_to:   r.station_to   ?? null,
          note:         r.note         ?? null,
          cost:         Number(r.cost ?? 0),
          trip_type:    r.trip_type || 'round_trip',
          category:     r.category  || 'regular',
          commuter_pass_id: r.commuter_pass_id ?? null,
          seq:          Number(r.seq ?? 0),
        };
        tx.push(apiUpdate(id, payload));
      }

      if (tx.length === 0) { alert('変更がありません'); return; }

      await Promise.all(tx);
      dirtyIds.clear();
      if (typeof table.clearEdited === 'function') table.clearEdited();
      alert('保存しました');
    } catch (err) {
      alert(err?.message || '保存に失敗しました');
    }
  });

  // ＋指定日を追加
  document.getElementById('addByDateBtn')?.addEventListener('click', async ()=>{
    const inp = document.getElementById('pickDate');
    const ymd = toYMDLocal(inp?.value);
    if (!ymd) { alert('日付を選択してください。'); return; }

    const [yy, mm] = ymd.split('-').map(Number);
    if (yy !== year || mm !== month) {
      if (!confirm('レポートの年月と異なる日付です。追加しますか？')) return;
    }

    const seq = maxSeqForDate(ymd) + 1024;
    const payload = {
      expense_report_id: reportId, expense_date: ymd, seq,
      station_from: null, station_to: null, note: null,
      cost: 0, trip_type: 'round_trip', category: 'regular',
      commuter_pass_id: null,
    };
    try {
      const created = await apiCreate(payload);
      table.addRow(normalizeRow(created));        // _sort 付き
      forceSortKey();

      const all = table.getRows();
      table.scrollToRow(all[all.length-1], 'nearest', true);
      recalcFooterSum();
    } catch (err) {
      alert(err?.message || '追加に失敗しました');
    }
  });

  // 初回合計
  recalcFooterSum();

  // 提出後の操作無効化（ボタン）
  if (isLocked){
    document.getElementById('addByDateBtn')?.setAttribute('disabled','disabled');
    document.getElementById('saveBtn')?.setAttribute('disabled','disabled');
  }
});
</script>

@endsection
