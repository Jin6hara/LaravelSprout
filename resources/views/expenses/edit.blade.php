@extends('layouts.app')

@push('styles')
<link href="https://unpkg.com/tabulator-tables@5.5.2/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .row-gray {
        opacity: .55;
    }
</style>
@endpush

@section('content')
<div class="container">
    <h3 class="mb-2">
        交通費編集 / {{ $report->employee_code }} {{ $report->employee_family_name }} {{ $report->employee_first_middle_name }}
        （{{ $report->year }}-{{ sprintf('%02d',$report->month) }}）
    </h3>
    <div class="mb-2">合計: <span id="totalAmount">{{ number_format($report->total_amount) }}</span> 円 / ステータス: {{ $report->status->value ?? $report->status }}</div>

    <div class="mb-3 d-flex align-items-end gap-2 flex-wrap">
        <button id="btnEdit" class="btn btn-primary btn-sm">編集をする</button>

        <div>
            <label class="form-label mb-1">日付追加</label>
            <div class="d-flex gap-2">
                <input type="date" id="addDate" class="form-control form-control-sm">
                <button id="btnAddDate" type="button" class="btn btn-outline-secondary btn-sm">行を追加</button>
            </div>
        </div>

        <button id="btnSave" class="btn btn-warning btn-sm" disabled>保存する</button>

        <form id="submitForm" class="d-inline" method="post" action="{{ route('expense.reports.submit',$report) }}">
            @csrf
            <button class="btn btn-success btn-sm">提出する</button>
        </form>
    </div>

    <div id="expenseTable"></div>
</div>
@endsection

@push('styles')
<link href="https://unpkg.com/tabulator-tables@5.5.2/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .row-gray {
        opacity: .55;
    }

    .row-dirty {
        outline: 2px dashed #f0ad4e;
        outline-offset: -2px;
    }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>
<script>
    const report = @json($report);
    let rows = @json($expenses);

    let table, editable = false,
        flags = {};

    async function fetchFlags() {
        const res = await fetch(`/expense-reports/${report.id}/flags`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (!res.ok) throw new Error('flags fetch failed');
        return await res.json();
    }

    function mergeFlags(rows, flags) {
        return rows.map(r => {
            const k = (r.expense_date || '').slice(0, 10);
            const f = flags[k] || {};
            return {
                ...r,
                normal: !!f.normal,
                reason: f.reason || null,
                _dirty: false
            };
        });
    }

    function rowFormatter(row) {
        const d = row.getData();
        if (d.normal === false) row.getElement().classList.add('row-gray');
        if (d._dirty === true) row.getElement().classList.add('row-dirty');
    }

    function recalcTotal() {
        if (!table) return;
        const sum = table.getData().reduce((a, b) => a + (parseInt(b.cost || 0) || 0), 0);
        document.getElementById('totalAmount').innerText = new Intl.NumberFormat().format(sum);
    }

    function buildColumns(editable) {
        return [{
                title: "日付",
                field: "expense_date",
                width: 120,
                sorter: "date"
            },
            {
                title: "From",
                field: "station_from",
                editor: editable ? "input" : false
            },
            {
                title: "To",
                field: "station_to",
                editor: editable ? "input" : false
            },
            {
                title: "金額",
                field: "cost",
                hozAlign: "right",
                sorter: "number",
                editor: editable ? "number" : false
            },
            {
                title: "往復",
                field: "trip_type",
                editor: editable ? "select" : false,
                editorParams: {
                    values: {
                        "round_trip": "往復",
                        "one_way": "片道"
                    }
                },
                formatter: (c) => c.getValue() === 'one_way' ? '片道' : '往復'
            },
            {
                title: "区分",
                field: "category",
                editor: editable ? "select" : false,
                editorParams: {
                    values: {
                        "regular": "レギュラー",
                        "irregular": "イレギュラー"
                    }
                },
                formatter: (c) => c.getValue() === 'irregular' ? 'イレ' : 'レギュ'
            },
            {
                title: "備考",
                field: "note",
                editor: editable ? "input" : false
            },
            {
                title: "",
                field: "normal",
                width: 60,
                hozAlign: "center",
                formatter: (c) => c.getValue() ? '' : '⚠',
                tooltip: (cell) => (cell.getRow().getData().reason || '注意')
            },
            {
                title: "操作",
                field: "_op",
                width: 150,
                headerSort: false,
                formatter: () => {
                    return `<button class="btn btn-sm btn-outline-primary js-dup">同日追加</button>
              <button class="btn btn-sm btn-outline-danger js-del">削除</button>`;
                }
            }
        ];
    }

    function rebuildColumns() {
        table.setColumns(buildColumns(editable));
        table.redraw(true);
    }

    function markDirty(row) {
        const d = row.getData();
        d._dirty = true;
        row.update(d);
        document.getElementById('btnSave').disabled = false;
    }

    async function saveDirtyRows() {
        const dirtyRows = table.getRows().filter(r => r.getData()._dirty);
        if (dirtyRows.length === 0) return true;

        for (const r of dirtyRows) {
            const d = r.getData();
            const res = await fetch(`/expenses/${d.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(d)
            });
            if (!res.ok) {
                alert('一部の行の保存に失敗しました');
                return false;
            }
            // 成功: dirty解除
            d._dirty = false;
            r.update(d);
        }
        document.getElementById('btnSave').disabled = true;
        recalcTotal();
        return true;
    }

    // === イベント配線 ===
    document.getElementById('btnEdit').addEventListener('click', () => {
        editable = !editable;
        rebuildColumns();
    });

    document.getElementById('btnSave').addEventListener('click', async () => {
        await saveDirtyRows();
    });

    // 提出時：まず保存→成功したら提出
    document.getElementById('submitForm').addEventListener('submit', async (e) => {
        if (document.getElementById('btnSave').disabled) return; // 変更なし
        e.preventDefault();
        const ok = await saveDirtyRows();
        if (ok) e.target.submit();
    });

    document.getElementById('btnAddDate').addEventListener('click', async () => {
        const inp = document.getElementById('addDate');
        const ds = inp.value;
        if (!ds) {
            alert('日付を選択してください');
            return;
        }

        // 同月チェック（軽め）
        const ym = ds.split('-');
        const y = parseInt(ym[0] || 0),
            m = parseInt(ym[1] || 0);
        if (y !== report.year || m !== report.month) {
            if (!confirm('選択した日付はこのレポートの年月と異なります。続行しますか？')) return;
        }

        // flags を使って警告
        const normal = !!(flags[ds] && flags[ds].normal);
        if (!normal) {
            if (!confirm('この日は灰色（推奨外）です。追加しますか？')) return;
        }

        // サーバーへ新規作成
        const payload = {
            expense_report_id: report.id,
            expense_date: ds,
            station_from: null,
            station_to: null,
            note: null,
            cost: 0,
            trip_type: 'round_trip',
            category: 'regular',
            commuter_pass_id: null
        };

        const res = await fetch('/expenses', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        });
        if (!res.ok) {
            alert('追加に失敗しました');
            return;
        }
        const newRow = await res.json();

        // 画面へ反映（flags も考慮）
        const merged = {
            ...newRow,
            normal,
            reason: (flags[ds]?.reason || null),
            _dirty: false
        };
        table.addRow(merged, true); // 末尾
        recalcTotal();
        if (!editable) {
            editable = true;
            rebuildColumns();
        } // 追加したら編集ONにしても良い
        inp.value = '';
    });

    (async () => {
        try {
            flags = await fetchFlags();
        } catch (e) {
            console.warn(e);
            flags = {};
        }
        rows = mergeFlags(rows, flags);

        table = new Tabulator("#expenseTable", {
            data: rows,
            layout: "fitColumns",
            index: "id",
            height: "auto",
            initialSort: [{
                column: "expense_date",
                dir: "asc"
            }],
            rowFormatter: rowFormatter,
            columns: buildColumns(false),

            // ここでは即保存しない（下書き保存ボタンでまとめて）
            cellEdited: function(cell) {
                markDirty(cell.getRow());
                recalcTotal();
            },

            rowClick: function(e, row) {
                const target = e.target;
                const d = row.getData();

                if (target.classList.contains('js-dup')) {
                    const payload = {
                        expense_report_id: report.id,
                        expense_date: d.expense_date,
                        station_from: d.station_from,
                        station_to: d.station_to,
                        note: d.note,
                        cost: d.cost,
                        trip_type: d.trip_type,
                        category: d.category,
                        commuter_pass_id: d.commuter_pass_id || null
                    };
                    fetch('/expenses', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(payload)
                    }).then(r => r.json()).then(newRow => {
                        const ds = newRow.expense_date?.slice(0, 10);
                        const merged = {
                            ...newRow,
                            normal: !!(flags[ds]?.normal),
                            reason: (flags[ds]?.reason || null),
                            _dirty: false
                        };
                        table.addRow(merged, true);
                        recalcTotal();
                    });
                }

                if (target.classList.contains('js-del')) {
                    if (!confirm('削除しますか？')) return;
                    fetch(`/expenses/${d.id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        })
                        .then(() => {
                            row.delete();
                            recalcTotal();
                        });
                }
            }
        });

        table.on("dataChanged", recalcTotal);
    })();
</script>
@endpush