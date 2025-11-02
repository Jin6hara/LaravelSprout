// === Ctrl+S / Cmd+S 完全ブロック（キャプチャで先取り） ===
(function () {
    function blockSave(e) {
        const isSaveKey =
            (e.key && (e.key === 's' || e.key === 'S')) ||
            (e.code && e.code === 'KeyS');

        if ((e.ctrlKey || e.metaKey) && isSaveKey) {
            e.preventDefault();
            e.stopImmediatePropagation(); // 他のハンドラにも渡さない
            return false;
        }
    }

    // window と document の両方、keydown/keypress の両方、キャプチャで登録
    window.addEventListener('keydown', blockSave, { capture: true });
    window.addEventListener('keypress', blockSave, { capture: true });
    document.addEventListener('keydown', blockSave, { capture: true });
    document.addEventListener('keypress', blockSave, { capture: true });
})();

document.addEventListener('DOMContentLoaded', function () {
    // === 月検索 ===
    const monthInput = document.getElementById('monthPick');
    const btn = document.getElementById('monthSearchBtn');

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
    monthInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });

    // === データ受け取り ===
    const dataEl = document.getElementById('expenseReportData');
    if (!dataEl) return;

    let rows = [];
    try {
        rows = JSON.parse(dataEl.textContent || '[]');
    } catch (e) {
        console.error('Failed to parse expenseReportData JSON', e);
        rows = [];
    }

    const yy = Number(dataEl.dataset.year || '0');
    const mm = Number(dataEl.dataset.month || '0');

    // DetailsボタンHTML
    function detailsBtn(code) {
        const safe = encodeURIComponent(String(code ?? ''));
        const href = `/expenses/${safe}/edit?year=${yy}&month=${mm}`;
        return `<a href="${href}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Details</a>`;
    }

    // 表データ生成
    const matrix = rows.map(r => ([
        detailsBtn(r.employee_code),
        r.employee_code ?? '',
        r.family_name ?? '',
        r.first_name ?? '',
        Number(r.total_amount ?? 0),
        (r.status || '').toUpperCase(),
        r.submitted_at ?? ''
    ]));

    // === jSpreadsheet 初期化 ===
    const el = document.getElementById('sheet');
    if (!el) return;

    jspreadsheet(el, {
        worksheets: [{
            data: matrix,
            columns: [
                { title: 'Details', width: 90, readOnly: true, type: 'html' },
                { title: 'Employee Code', width: 150, readOnly: true },
                { title: 'Family Name', width: 150, readOnly: true },
                { title: 'First (Middle) Name', width: 220, readOnly: true },
                { title: 'Total (JPY)', width: 140, readOnly: true, type: 'numeric', mask: '#,##0' },
                { title: 'Status', width: 140, readOnly: true },
                { title: 'Submitted At', width: 157, readOnly: true },
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
            tableOverflow: false,
            tableHeight: '470px',
        }],
    });

    // 参考: 合計（未使用・必要なら利用）
    // const total = sheet[0].getColumnData(3).reduce((a, v) => a + (+v || 0), 0);
});
