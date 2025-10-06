@extends('layouts.app')

@section('title', '交通費（表示のみ）')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
<style>
  .page-wrap {
    max-width: 1100px;
    margin: 20px auto;
  }

  .header-box {
    background: #f8f9fa;
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid #78a3faff;
  }

  .header-box .meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 14px;
  }

  .total {
    font-weight: 700;
  }

  .ag-theme-alpine {
    height: 600px;
    width: 100%;
  }

  .row-actions {
    display: flex;
    gap: 6px;
    justify-content: center;
  }

  .row-actions button {
    font-size: 12px;
    padding: 2px 6px;
  }

  .ag-theme-alpine .ag-row.row-on .ag-cell {
    background-color: #ecfdf5 !important;
  }

  .ag-theme-alpine .ag-row.row-off .ag-cell {
    background-color: #f3f4f6 !important;
    color: #6b7280;
  }

  .muted {
    color: #6b7280;
  }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community/dist/ag-grid-community.min.js"></script>
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
      {{-- JS無効時用：送信用のyear/monthをhiddenに入れてsubmit --}}
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
      <input type="date" id="pickDate" class="form-control form-control-sm" style="width: 160px;">
      <button id="addByDateBtn" class="btn btn-success btn-sm">＋指定日を追加</button>
      <button id="saveBtn" class="btn btn-primary btn-sm">保存</button>
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

  <div id="expensesGrid" class="ag-theme-alpine"></div>
</div>

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
    url.searchParams.set('month', Number(mm)); // "09"→9 に
    window.location = url.toString();
  }

  btn?.addEventListener('click', (e) => { e.preventDefault(); doSearch(); });

  // Enterキーで検索したい場合（任意）
  monthInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
  });
});
</script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const eventOnMap = @json($eventOnMap ?? []);
    const initialRows = @json($rows);
    const reportId = @json($report->id ?? null);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    function isOnDay(ymd) {
      return !!eventOnMap[ymd];
    }

    // ===== utils =====
    const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    function toYMDLocal(v) {
      if (!v) return v;
      if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v)) return v;
      const d = new Date(v);
      if (isNaN(d)) return String(v).slice(0, 10);
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${y}-${m}-${day}`;
    }

    function compareDateYMD(a, b) {
      if (a === b) return 0;
      return a < b ? -1 : 1;
    }

    function normalizeRow(r) {
      return {
        ...r,
        expense_date: toYMDLocal(r.expense_date),
        seq: Number(r.seq ?? 100),
        cost: Number(r.cost ?? 0),
      };
    }

    function fmtInt(n) {
      return (n ?? 0).toLocaleString();
    }

    function recalcFooterSum(api) {
      let sum = 0;
      api.forEachNodeAfterFilterAndSort(node => sum += Number(node.data?.cost ?? 0));
      const el = document.getElementById('sumCost');
      if (el) el.innerText = fmtInt(sum);
    }

    // ===== API helpers =====
    async function apiCreate(payload) {
      const res = await fetch(@json(route('api.expenses.store')), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload),
      });
      if (!res.ok) throw await res.json().catch(() => ({
        message: 'Create failed'
      }));
      return res.json();
    }
    async function apiUpdate(id, payload) {
      const url = @json(route('api.expenses.update', ['expense' => '__ID__'])).replace('__ID__', id);
      const res = await fetch(url, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload),
      });
      if (!res.ok) throw await res.json().catch(() => ({
        message: 'Update failed'
      }));
      return res.json();
    }
    async function apiDelete(id) {
      const url = @json(route('api.expenses.destroy', ['expense' => '__ID__'])).replace('__ID__', id);
      const res = await fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        }
      });
      if (!res.ok) throw await res.json().catch(() => ({
        message: 'Delete failed'
      }));
      return res.json();
    }

    // ===== column defs =====
    const columnDefs = [{
        headerName: '',
        field: '_actions',
        width: 80,
        sortable: false,
        filter: false,
        cellRenderer: (p) => {
          const wrap = document.createElement('div');
          wrap.className = 'row-actions';
          const addBtn = document.createElement('button');
          addBtn.textContent = '＋';
          const delBtn = document.createElement('button');
          delBtn.textContent = 'ー';
          wrap.appendChild(addBtn);
          wrap.appendChild(delBtn);

          addBtn.addEventListener('click', async () => {
            const base = p.data;
            const d = toYMDLocal(base.expense_date);
            const curSeq = Number(base.seq ?? 100);

            // 同日の seq を収集して中間 or +100
            const sameDaySeqs = [];
            gridApi.forEachNodeAfterFilterAndSort(n => {
              if (toYMDLocal(n.data.expense_date) === d) sameDaySeqs.push(Number(n.data.seq ?? 0));
            });
            sameDaySeqs.sort((a, b) => a - b);
            let nextSeq = null;
            for (const s of sameDaySeqs) {
              if (s > curSeq) {
                nextSeq = s;
                break;
              }
            }
            const newSeq = (nextSeq === null) ? curSeq + 100 : (nextSeq - curSeq > 1 ? Math.floor((curSeq + nextSeq) / 2) : curSeq + 100);

            try {
              const created = await apiCreate({
                expense_report_id: reportId,
                expense_date: d,
                seq: newSeq,
                station_from: null,
                station_to: null,
                note: null,
                cost: 0,
                trip_type: 'round_trip',
                category: 'regular',
                commuter_pass_id: null,
              });
              const row = normalizeRow(created);
              gridApi.applyTransaction({
                add: [row]
              });

              // ★ 並べ替えを強制再実行（setSortModel は使わない）
              if (gridApi.refreshClientSideRowModel) gridApi.refreshClientSideRowModel('sort');

              recalcFooterSum(gridApi);
            } catch (err) {
              alert(err.message || 'Add failed');
            }
          });

          delBtn.addEventListener('click', async () => {
            const row = p.data;
            if (!row.id) {
              gridApi.applyTransaction({
                remove: [row]
              });
              return;
            }
            if (!confirm('この行を削除しますか？')) return;
            try {
              await apiDelete(row.id);
              gridApi.applyTransaction({
                remove: [row]
              });
              if (gridApi.refreshClientSideRowModel) gridApi.refreshClientSideRowModel('sort');
              recalcFooterSum(gridApi);
            } catch (err) {
              alert(err.message || 'Delete failed');
            }
          });

          return wrap;
        }
      },

      // 日付（初期ソート: sort=asc, sortIndex=0）
      {
        headerName: '日付',
        field: 'expense_date',
        width: 120,
        editable: false,
        valueGetter: (p) => toYMDLocal(p.data.expense_date),
        comparator: (a, b) => compareDateYMD(a, b),
        sort: 'asc', // ★ 初期ソート
        sortIndex: 0, // ★ 優先順位1位
      },
      {
        headerName: 'Weekday',
        field: 'weekday',
        width: 100,
        editable: false,
        valueGetter: (p) => {
          const d = toYMDLocal(p.data.expense_date);
          if (!d) return '';
          return weekdayNames[new Date(d + 'T00:00:00').getDay()] ?? '';
        },
      },

      // 並び用（非表示, 初期ソート: sortIndex=1）
      {
        headerName: 'seq',
        field: 'seq',
        hide: true,
        comparator: (a, b) => Number(a ?? 0) - Number(b ?? 0),
        valueGetter: (p) => Number(p.data.seq ?? 0),
        sort: 'asc', // ★ 初期ソート
        sortIndex: 1, // ★ 優先順位2位
      },

      // 入力列
      {
        headerName: 'From',
        field: 'station_from',
        width: 140,
        editable: true
      },
      {
        headerName: 'To',
        field: 'station_to',
        width: 140,
        editable: true
      },
      {
        headerName: 'Amount',
        field: 'cost',
        width: 100,
        editable: true,
        valueFormatter: (p) => p.value != null ? Number(p.value).toLocaleString() : '',
        valueParser: (p) => Number(p.newValue ?? 0),
      },
      {
        headerName: 'Type',
        field: 'trip_type',
        width: 100,
        editable: true,
        cellEditor: 'agSelectCellEditor',
        cellEditorParams: {
          values: ['round_trip', 'one_way']
        }
      },
      {
        headerName: '！',
        field: 'category',
        width: 100,
        editable: true,
        cellEditor: 'agSelectCellEditor',
        cellEditorParams: {
          values: ['regular', 'irregular']
        }
      },
      {
        headerName: 'Note',
        field: 'note',
        flex: 1,
        minWidth: 200,
        editable: true
      },
    ];

    function maxSeqForDate(ymd) {
      let max = 0;
      gridApi.forEachNodeAfterFilterAndSort(n => {
        if (toYMDLocal(n.data.expense_date) === ymd) {
          const s = Number(n.data.seq ?? 0);
          if (s > max) max = s;
        }
      });
      return max;
    }

    document.getElementById('addByDateBtn')?.addEventListener('click', async () => {
      const input = document.getElementById('pickDate');
      const ymd = toYMDLocal(input?.value);
      if (!ymd) return alert('日付を選択してください。');

      // レポートの年月チェック
      const [yy, mm] = ymd.split('-').map(v => Number(v));
      if (yy !== Number(@json($y)) || mm !== Number(@json($m))) {
        if (!confirm('レポートの年月と異なる日付です。追加しますか？')) return;
      }

      // Eligibility（任意）：もし flags を用意していたら警告
      if (window.expenseFlags && window.expenseFlags[ymd] && window.expenseFlags[ymd].normal === false) {
        const reason = window.expenseFlags[ymd].reason || 'eligible=false';
        if (!confirm(`注意：この日は通常勤務扱いではありません（${reason}）。追加しますか？`)) return;
      }

      // 同日最大 seq + 1024
      const seq = maxSeqForDate(ymd) + 1024;

      try {
        const created = await apiCreate({
          expense_report_id: reportId,
          expense_date: ymd,
          seq: seq,
          station_from: null,
          station_to: null,
          note: null,
          cost: 0,
          trip_type: 'round_trip',
          category: 'regular',
          commuter_pass_id: null,
        });

        const row = normalizeRow(created);
        gridApi.applyTransaction({
          add: [row]
        });
        if (gridApi.refreshClientSideRowModel) gridApi.refreshClientSideRowModel('sort');

        // 追加した行へスクロール＆選択
        gridApi.ensureIndexVisible(gridApi.getDisplayedRowCount() - 1);
      } catch (err) {
        alert(err?.message || '追加に失敗しました');
      }
    });

    // ===== grid init =====
    const gridOptions = {
      theme: 'legacy', // ag-grid.css を使い続ける
      columnDefs,
      rowData: initialRows.map(normalizeRow),
      defaultColDef: {
        sortable: true,
        filter: false,
        resizable: true
      },
      suppressClickEdit: false,
      animateRows: true,
      rowSelection: {
        mode: 'singleRow'
      }, // ★ 新APIに合わせる
      rowClassRules: {
        'row-on': p => isOnDay(p.data?.expense_date), // 緑
        'row-off': p => !isOnDay(p.data?.expense_date), // 灰
      },
      onGridReady: (params) => {
        recalcFooterSum(params.api);
      },
      onCellValueChanged: (e) => {
        if (e.data?.id) dirtyIds.add(e.data.id);
        if (e.colDef.field === 'cost') recalcFooterSum(gridApi);
      },
    };

    const gridDiv = document.getElementById('expensesGrid');
    let gridApi;
    try {
      if (window.agGrid && typeof agGrid.createGrid === 'function') {
        gridApi = agGrid.createGrid(gridDiv, gridOptions);
      } else {
        new agGrid.Grid(gridDiv, gridOptions);
        gridApi = gridOptions.api; // 旧API
      }
    } catch (e) {
      console.error('AG Grid init error:', e);
      gridDiv.innerHTML = '<div style="padding:12px;color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;border-radius:8px">Gridの初期化に失敗しました。コンソールのエラーをご確認ください。</div>';
      return;
    }

    // 変更追跡 & 保存
    const dirtyIds = new Set();
    document.getElementById('saveBtn')?.addEventListener('click', async () => {
      const tx = [];
      gridApi.forEachNode(n => {
        if (n.data?.id && dirtyIds.has(n.data.id)) {
          tx.push(apiUpdate(n.data.id, {
            station_from: n.data.station_from ?? null,
            station_to: n.data.station_to ?? null,
            note: n.data.note ?? null,
            cost: Number(n.data.cost ?? 0),
            trip_type: n.data.trip_type,
            category: n.data.category,
            commuter_pass_id: n.data.commuter_pass_id ?? null,
            seq: Number(n.data.seq ?? 100),
          }));
        }
      });
      try {
        await Promise.all(tx);
        dirtyIds.clear();
        alert('保存しました');
      } catch (err) {
        alert(err.message || '保存に失敗しました');
      }
    });
  });
</script>

@endsection