@extends('layouts.app')

@section('title', 'Events/Subs Editor')

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css">
  <style>
    .sheet-toolbar { margin-bottom: .75rem; display:flex; gap:.5rem; align-items:center; flex-wrap: wrap; }
    .btn { padding:.4rem .7rem; border:1px solid #ddd; border-radius:.5rem; background:#fff; cursor:pointer; }
    .btn:hover { background:#f5f5f5; }
    .status-badge { padding: .1rem .4rem; font-size: .75rem; border-radius: .375rem; background:#eef2ff; }
  </style>
@endpush

@section('content')
@php
  $cur = \Carbon\Carbon::parse($date);
  $prev = $cur->copy()->subDay()->toDateString();
  $next = $cur->copy()->addDay()->toDateString();
@endphp

<div class="container">
  <h1 class="h4 mb-3">
    Events/Subs Editor
    <span class="status-badge">{{ $date }}</span>
  </h1>

  <form class="mb-3" method="get" action="{{ route('events.edit') }}" style="display:flex; gap:.5rem; align-items:center;">
    <a class="btn" href="{{ route('events.edit', ['date' => $prev]) }}">&laquo; Prev</a>
    <label class="me-2">Date</label>
    <input type="date" name="date" value="{{ $date }}" />
    <button class="btn" type="submit">Go</button>
    <a class="btn" href="{{ route('events.edit', ['date' => $next]) }}">Next &raquo;</a>
    <a class="btn" href="{{ route('events.edit', ['date' => now()->toDateString()]) }}">Today</a>
  </form>

  <div class="sheet-toolbar">
    <button id="saveBtn" class="btn">Save</button>
    <button id="reloadBtn" class="btn" onclick="location.href='{{ route('events.edit', ['date'=>$date]) }}'">Reload</button>
  </div>

  <div id="sheet"></div>
</div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/jsuites/dist/jsuites.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js"></script>
  <script>
    const CUR_DATE = @json($date); // ← 当日

    // --- サーバーから渡された初期データ ---
    const SUMMARY = @json($summaryRows); // 1行だけ
    const EVENTS  = @json($eventRows);

    function enWeekday(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr + 'T00:00:00');
      if (isNaN(d)) return '';
      return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()];
    }

    // 1日分の Summary
    for (const r of SUMMARY) r.weekday = enWeekday(r.date);
    for (const r of EVENTS)  r.weekday = enWeekday(r.event_date);

    const summaryMatrix = SUMMARY.map(r => [r.date, r.weekday, r.subs_total, r.events_total]);
    const summaryColumns = [
      { type:'calendar', title:'Date', width:120, readOnly:true, options:{ format:'YYYY-MM-DD' } },
      { type:'text',     title:'Weekday', width:90, readOnly:true },
      { type:'numeric',  title:'Subs(total)', width:120, readOnly:true },
      { type:'numeric',  title:'Events(total)', width:120, readOnly:true },
    ];

    const SUB_OPTIONS   = [{id:'none_required',name:'none_required'}, {id:'required',name:'required'}, {id:'other',name:'other'}];
    const STATUS_OPTIONS= [{id:'pending',name:'pending'}, {id:'fixed',name:'fixed'}, {id:'filled',name:'filled'}, {id:'in_process',name:'in_process'}];
    const TYPE_OPTIONS  = [{id:'regular_time',name:'regular_time'}, {id:'overtime',name:'overtime'}, {id:'schedule_change',name:'schedule_change'}, {id:'special',name:'special'}];

    const eventMatrix = EVENTS.map(r => [
      r.id ?? '',                  // 0: id(hidden)
      '',                          // 1: actions
      r.event_date ?? CUR_DATE,    // 2: Date（既存がnullでも当日を初期値に）
      r.weekday ?? enWeekday(r.event_date ?? CUR_DATE),
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
      { type:'text', title:'ID', width:60, readOnly:true },
      { type:'text', title:'', width:40, readOnly:true },
      { type:'calendar', title:'Date', width:120, options:{ format:'YYYY-MM-DD' } },
      { type:'text',     title:'Weekday', width:80, readOnly:true },
      { type:'numeric',  title:'OriginalUser', width:110 },
      { type:'text',     title:'LeaveType', width:110 },
      { type:'dropdown', title:'Sub', width:120, source: SUB_OPTIONS, autocomplete:true },
      { type:'text',     title:'Title', width:140 },
      { type:'text',     title:'School', width:160 },
      { type:'text',     title:'Start(HH:MM)', width:110, mask:'00:00' },
      { type:'text',     title:'End(HH:MM)', width:110, mask:'00:00' },
      { type:'numeric',  title:'Total(min)', width:100, readOnly:true },
      { type:'text',     title:'Lesson', width:140 },
      { type:'numeric',  title:'AssignedUser', width:120 },
      { type:'dropdown', title:'Status', width:120, source: STATUS_OPTIONS },
      { type:'dropdown', title:'Type', width:140, source: TYPE_OPTIONS },
      { type:'text',     title:'Notes', width:200 },
    ];

    function blankEventRow() {
      return ['', '', CUR_DATE, enWeekday(CUR_DATE), '', '', 'none_required', '', '', '', '', '', '', '', 'pending', 'regular_time', ''];
    }

    const el = document.getElementById('sheet');
    const sheet = jspreadsheet(el, {
      worksheets: [
        {
          worksheetName: 'Summary',
          data: summaryMatrix,
          columns: summaryColumns,
          minDimensions: [summaryColumns.length, 1],
          tableOverflow: true,
          tableHeight: '140px', // 1日なので小さめ
        },
        {
          worksheetName: 'Events',
          data: eventMatrix.length ? eventMatrix : [blankEventRow()],
          columns: eventColumns,
          minDimensions: [eventColumns.length, Math.max(5, eventMatrix.length || 5)],
          tableOverflow: true,
          tableHeight: '440px',
          onchange: function(w, cell, x, y, value) {
            if (x === 2) { // Date col
              const row = w.getRowData(y);
              const dateStr = row[2] || CUR_DATE;
              w.setValueFromCoords(3, y, enWeekday(dateStr), true);
            }
          },
          updateTable: function(w, cell, x, y, source) {
            if (y >= 0 && x === 1) {
              cell.innerHTML = '<button data-row="'+y+'" class="btn btn-sm btn-delete" style="padding:.2rem .4rem;">Del</button>';
            }
          },
          onload: function(w) {
            const toolbar = document.querySelector('.sheet-toolbar');
            const addBtn = document.createElement('button');
            addBtn.className = 'btn';
            addBtn.textContent = 'Add Row';
            addBtn.onclick = () => w.insertRow(blankEventRow(), w.getJson().length);
            toolbar.appendChild(addBtn);

            w.el.addEventListener('click', (e) => {
              const btn = e.target.closest('.btn-delete');
              if (!btn) return;
              const rowIdx = parseInt(btn.getAttribute('data-row'), 10);
              if (Number.isNaN(rowIdx)) return;

              const row = w.getRowData(rowIdx);
              const id  = row[0];
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

    // Save：当日の行だけをPOST/PUT
    document.getElementById('saveBtn').addEventListener('click', async () => {
      const w = sheet.worksheets[1];
      const rows = w.getJson();
      const tasks = [];

      for (let i=0; i<rows.length; i++) {
        const r = rows[i];

        const payload = {
          event_date:       r[2] || CUR_DATE,
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
        if (!payload.event_date) continue;

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
            w.setValueFromCoords(0, i, js.id, true);
          }
        }));
      }

      await Promise.all(tasks);
      // 1日表示なので、同じdateでリロード
      location.href = `{{ route('events.edit') }}?date=${encodeURIComponent(CUR_DATE)}`;
    });
  </script>
@endpush
