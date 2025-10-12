@extends('layouts.app')

@section('title', 'Events/Subs Editor')

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
  <style>
    .sheet-toolbar { margin-bottom: .75rem; display:flex; gap:.5rem; align-items:center; }
    .btn { padding:.4rem .7rem; border:1px solid #ddd; border-radius:.5rem; background:#fff; cursor:pointer; }
    .btn:hover { background:#f5f5f5; }
    .status-badge { padding: .1rem .4rem; font-size: .75rem; border-radius: .375rem; background:#eef2ff; }
  </style>
@endpush

@section('content')
<div class="container">
  <h1 class="h4 mb-3">Events/Subs Editor <span class="status-badge">{{ $month }}</span></h1>

  <form class="mb-3" method="get" action="{{ route('events.edit') }}">
    <label class="me-2">Month</label>
    <input type="month" name="month" value="{{ $month }}" />
    <button class="btn" type="submit">Go</button>
  </form>

  <div class="sheet-toolbar">
    <button id="saveBtn" class="btn">Save</button>
    <button id="reloadBtn" class="btn" onclick="location.reload()">Reload</button>
  </div>

  <div id="sheet"></div>
</div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js"></script>
  <script>
    // --- サーバーから渡された初期データ ---
    const SUMMARY = @json($summaryRows);
    const EVENTS  = @json($eventRows);

    // Weekday (en)
    function enWeekday(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr + 'T00:00:00');
      if (isNaN(d)) return '';
      return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()];
    }

    // 初期加工：Summaryのweekday付与
    for (const r of SUMMARY) r.weekday = enWeekday(r.date);
    for (const r of EVENTS)  r.weekday = enWeekday(r.event_date);

    // === Sheet1: Summary（読み取り専用） ===
    const summaryMatrix = SUMMARY.map(r => [r.date, r.weekday, r.subs_total, r.events_total]);

    const summaryColumns = [
      { type:'calendar', title:'Date', width:110, readOnly:true, options:{ format:'YYYY-MM-DD' } },
      { type:'text',     title:'Weekday', width:90, readOnly:true },
      { type:'numeric',  title:'Subs(total)', width:110, readOnly:true },
      { type:'numeric',  title:'Events(total)', width:120, readOnly:true },
    ];

    // === Sheet2: Events（編集用） ===
    // 列定義は DBスキーマに合わせる
    const SUB_OPTIONS   = [{id:'none_required',name:'none_required'}, {id:'required',name:'required'}, {id:'other',name:'other'}];
    const STATUS_OPTIONS= [{id:'pending',name:'pending'}, {id:'fixed',name:'fixed'}, {id:'filled',name:'filled'}, {id:'in_process',name:'in_process'}];
    const TYPE_OPTIONS  = [{id:'regular_time',name:'regular_time'}, {id:'overtime',name:'overtime'}, {id:'schedule_change',name:'schedule_change'}, {id:'special',name:'special'}];

    // 行を matrix へ
    const eventMatrix = EVENTS.map(r => [
      r.id ?? '',                  // 0: id(hidden)
      '',                          // 1: actions (削除ボタン列)
      r.event_date ?? '',
      r.weekday ?? '',
      r.original_user_id ?? '',
      r.Leave_type ?? '',
      r.sub ?? 'none_required',
      r.title ?? '',
      r.school_name ?? '',
      r.start_time ?? '',
      r.end_time ?? '',
      r.total_minutes ?? '',
      r.Lesson ?? '',
      r.assigned_user_id ?? '',
      r.status ?? 'pending',
      r.type ?? 'regular_time',
      r.notes ?? '',
    ]);

    const eventColumns = [
      { type:'text', title:'ID', width:60, readOnly:true },                   // 0 hidden via style
      { type:'text', title:'', width:40, readOnly:true },                     // 1 actions (削除ボタンを描画)
      { type:'calendar', title:'Date', width:110, options:{ format:'YYYY-MM-DD' } },
      { type:'text',     title:'Weekday', width:80, readOnly:true },
      { type:'numeric',  title:'OriginalUser', width:110 },
      { type:'text',     title:'LeaveType', width:110 },
      { type:'dropdown', title:'Sub', width:120, source: SUB_OPTIONS, autocomplete:true },
      { type:'text',     title:'Title', width:140 },
      { type:'text',     title:'School', width:160 },
      { type:'text',     title:'Start(HH:MM)', width:110, mask:'00:00' },
      { type:'text',     title:'End(HH:MM)', width:110, mask:'00:00' },
      { type:'numeric',  title:'Total(min)', width:100, readOnly:true }, // サーバー再計算だが表示はしておく
      { type:'text',     title:'Lesson', width:140 },
      { type:'numeric',  title:'AssignedUser', width:120 },
      { type:'dropdown', title:'Status', width:120, source: STATUS_OPTIONS },
      { type:'dropdown', title:'Type', width:140, source: TYPE_OPTIONS },
      { type:'text',     title:'Notes', width:200 },
    ];

    // 新規追加のための空行テンプレ
    function blankEventRow() {
      return ['', '', '', '', '', '', 'none_required', '', '', '', '', '', '', '', 'pending', 'regular_time', ''];
    }

    // worksheets 構築
    const el = document.getElementById('sheet');
    const sheet = jspreadsheet(el, {
      worksheets: [
        {
          worksheetName: 'Summary',
          data: summaryMatrix,
          columns: summaryColumns,
          minDimensions: [summaryColumns.length, Math.max(1, summaryMatrix.length)],
          tableOverflow: true,
          tableHeight: '240px',
        },
        {
          worksheetName: 'Events',
          data: eventMatrix.length ? eventMatrix : [blankEventRow()],
          columns: eventColumns,
          minDimensions: [eventColumns.length, Math.max(8, eventMatrix.length || 8)],
          tableOverflow: true,
          tableHeight: '420px',
          onchange: function(w, cell, x, y, value) {
            // Date → Weekday 自動更新
            if (x === 2) { // Date col
              const row = w.getRowData(y);
              const dateStr = row[2] || '';
              w.setValueFromCoords(3, y, enWeekday(dateStr), true); // Weekday col
            }
          },
          // 行ヘッダの代わりに削除ボタンを描画
          updateTable: function(w, cell, x, y, source) {
            if (y >= 0 && x === 1) { // actions列
              cell.innerHTML = '<button data-row="'+y+'" class="btn btn-sm btn-delete" style="padding:.2rem .4rem;">Del</button>';
            }
          },
          onload: function(w) {
            // 追加ボタン（最後に空行を追加）
            const toolbar = document.querySelector('.sheet-toolbar');
            const addBtn = document.createElement('button');
            addBtn.className = 'btn';
            addBtn.textContent = 'Add Row';
            addBtn.onclick = () => w.insertRow(blankEventRow(), w.getJson().length);
            toolbar.appendChild(addBtn);

            // 削除イベント委譲
            w.el.addEventListener('click', (e) => {
              const btn = e.target.closest('.btn-delete');
              if (!btn) return;
              const rowIdx = parseInt(btn.getAttribute('data-row'), 10);
              if (Number.isNaN(rowIdx)) return;

              const row = w.getRowData(rowIdx);
              const id  = row[0]; // id
              // 先にサーバー削除 → UIから行削除
              if (id) {
                fetch(`{{ route('api.events.destroy', ['event' => 'ID']) }}`.replace('ID', id), {
                  method: 'DELETE',
                  headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                }).then(r => r.json()).then(js => {
                  if (js.ok) w.deleteRow(rowIdx, 1);
                });
              } else {
                w.deleteRow(rowIdx, 1);
              }
            });
          },
        }
      ],
    });

    // Save：Eventsシートをスキャンし、id有→PUT、無→POST
    document.getElementById('saveBtn').addEventListener('click', async () => {
      const w = sheet.worksheets[1]; // Events
      const rows = w.getJson(); // 配列の配列
      const tasks = [];

      for (let i=0; i<rows.length; i++) {
        const r = rows[i];
        // マッピング（列→フィールド）
        const payload = {
          event_date:       r[2] || null,
          original_user_id: r[4] || null,
          Leave_type:       r[5] || null,
          sub:              r[6] || 'none_required',
          title:            r[7] || null,
          school_name:      r[8] || null,
          start_time:       r[9] || null,
          end_time:         r[10] || null,
          total_minutes:    r[11] || null, // サーバーで再計算
          Lesson:           r[12] || null,
          assigned_user_id: r[13] || null,
          status:           r[14] || 'pending',
          type:             r[15] || 'regular_time',
          notes:            r[16] || null,
        };

        const id = r[0];
        if (!payload.event_date) { continue; } // 空行はスキップ

        const opts = {
          method: id ? 'PUT' : 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
          body: JSON.stringify(payload),
        };
        const url = id
          ? `{{ route('api.events.update', ['event'=>'ID']) }}`.replace('ID', id)
          : `{{ route('api.events.store') }}`;

        tasks.push(fetch(url, opts).then(r => r.json()).then(js => {
          if (js.ok && js.id) {
            // 新規登録時にIDを反映
            w.setValueFromCoords(0, i, js.id, true);
          }
        }));
      }

      await Promise.all(tasks);
      // 再読込で Summary を最新化（Resolver再計算）
      location.href = `{{ route('events.edit') }}?month={{ $month }}`;
    });
  </script>
@endpush
