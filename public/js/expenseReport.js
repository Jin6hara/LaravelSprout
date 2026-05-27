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
            showToast('Please select a target month.', 'warning');
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

    // ▼ 画面幅1300px以下のとき、フォームにだけ下部横スクロール
    const monthForm = document.getElementById('monthForm');
    function updateFormScrollStyle() {
        if (!monthForm) return;
        if (window.innerWidth <= 1300) {
            monthForm.style.overflowX = 'auto';
            monthForm.style.display = 'block';
            monthForm.style.paddingBottom = '8px';
        } else {
            monthForm.style.overflowX = '';
            monthForm.style.display = '';
            monthForm.style.paddingBottom = '';
        }
    }
    updateFormScrollStyle();
    window.addEventListener('resize', updateFormScrollStyle);

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

    // ← Blade 側で埋めた件数（0 なら「レコードなし」）
    const count = Number(dataEl.dataset.count || rows.length || 0);

    // ✅ レコード無しのときはシートを描画しない（ヘッダの案内だけ表示）
    if (count === 0) return;

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

    const heightSelect = document.getElementById('expenseReportHeight');
    const savedHeight = localStorage.getItem('expenseReportHeight') || '560';
    if (heightSelect) {
        heightSelect.value = savedHeight;
    }

    const topScroll = document.createElement('div');
    topScroll.className = 'expense-report-top-scroll';
    const topScrollInner = document.createElement('div');
    topScrollInner.className = 'expense-report-top-scroll-inner';
    topScroll.appendChild(topScrollInner);

    const scrollWrapper = document.createElement('div');
    scrollWrapper.className = 'expense-report-scroll';
    scrollWrapper.style.cssText = 'width:100%;overflow:auto;-webkit-overflow-scrolling:touch;';
    el.parentNode.insertBefore(topScroll, el);
    topScroll.parentNode.insertBefore(scrollWrapper, el);
    scrollWrapper.appendChild(el);

    function applyTableHeight(value) {
        if (value === 'full') {
            scrollWrapper.style.maxHeight = '';
        } else {
            scrollWrapper.style.maxHeight = `${Number(value) || 560}px`;
        }
    }

    function syncTopScrollWidth() {
        topScrollInner.style.width = `${scrollWrapper.scrollWidth}px`;
    }

    function syncScrollLeft(source, target) {
        if (Math.abs(target.scrollLeft - source.scrollLeft) > 1) {
            target.scrollLeft = source.scrollLeft;
        }
    }

    function enableSelectionAutoScroll(wrapper, sheetEl) {
        let isSelecting = false;
        let scrollXSpeed = 0;
        let scrollYSpeed = 0;
        let frameId = null;
        const horizontalEdgeSize = 80;
        const verticalEdgeSize = 110;
        const maxHorizontalSpeed = 28;
        const maxVerticalSpeed = 24;

        function stopAutoScroll() {
            isSelecting = false;
            scrollXSpeed = 0;
            scrollYSpeed = 0;
            if (frameId) {
                window.cancelAnimationFrame(frameId);
                frameId = null;
            }
        }

        function scrollStep() {
            if (!isSelecting || (scrollXSpeed === 0 && scrollYSpeed === 0)) {
                frameId = null;
                return;
            }

            if (scrollXSpeed !== 0) {
                wrapper.scrollLeft += scrollXSpeed;
            }

            if (scrollYSpeed !== 0) {
                wrapper.scrollTop += scrollYSpeed;
            }

            frameId = window.requestAnimationFrame(scrollStep);
        }

        function updateAutoScroll(clientX, clientY) {
            if (!isSelecting) return;

            const rect = wrapper.getBoundingClientRect();
            const distanceLeft = clientX - rect.left;
            const distanceRight = rect.right - clientX;
            const distanceTop = clientY - rect.top;
            const distanceBottom = rect.bottom - clientY;

            if (distanceLeft >= 0 && distanceLeft < horizontalEdgeSize) {
                scrollXSpeed = -Math.ceil(((horizontalEdgeSize - distanceLeft) / horizontalEdgeSize) * maxHorizontalSpeed);
            } else if (distanceRight >= 0 && distanceRight < horizontalEdgeSize) {
                scrollXSpeed = Math.ceil(((horizontalEdgeSize - distanceRight) / horizontalEdgeSize) * maxHorizontalSpeed);
            } else {
                scrollXSpeed = 0;
            }

            if (distanceTop >= 0 && distanceTop < verticalEdgeSize) {
                scrollYSpeed = -Math.ceil(((verticalEdgeSize - distanceTop) / verticalEdgeSize) * maxVerticalSpeed);
            } else if (distanceBottom >= 0 && distanceBottom < verticalEdgeSize) {
                scrollYSpeed = Math.ceil(((verticalEdgeSize - distanceBottom) / verticalEdgeSize) * maxVerticalSpeed);
            } else {
                scrollYSpeed = 0;
            }

            if ((scrollXSpeed !== 0 || scrollYSpeed !== 0) && !frameId) {
                frameId = window.requestAnimationFrame(scrollStep);
            }
        }

        sheetEl.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            isSelecting = true;
            updateAutoScroll(e.clientX, e.clientY);
        }, true);

        document.addEventListener('mousemove', (e) => updateAutoScroll(e.clientX, e.clientY), true);
        document.addEventListener('mouseup', stopAutoScroll, true);
        window.addEventListener('blur', stopAutoScroll);
    }

    applyTableHeight(savedHeight);
    heightSelect?.addEventListener('change', () => {
        localStorage.setItem('expenseReportHeight', heightSelect.value);
        applyTableHeight(heightSelect.value);
        window.requestAnimationFrame(syncTopScrollWidth);
    });

    topScroll.addEventListener('scroll', () => syncScrollLeft(topScroll, scrollWrapper));
    scrollWrapper.addEventListener('scroll', () => syncScrollLeft(scrollWrapper, topScroll));

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

    window.requestAnimationFrame(syncTopScrollWidth);
    if (window.ResizeObserver) {
        const resizeObserver = new ResizeObserver(syncTopScrollWidth);
        resizeObserver.observe(el);
        resizeObserver.observe(scrollWrapper);
    } else {
        window.addEventListener('resize', syncTopScrollWidth);
    }

    enableSelectionAutoScroll(scrollWrapper, el);

    // 参考: 合計（未使用・必要なら利用）
    // const total = sheet[0].getColumnData(3).reduce((a, v) => a + (+v || 0), 0);
});
