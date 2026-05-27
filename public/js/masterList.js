// === Ctrl+S / Cmd+S block ===
(function () {
    function blockSave(e) {
        const isSaveKey =
            (e.key && (e.key === 's' || e.key === 'S')) ||
            (e.code && e.code === 'KeyS');

        if ((e.ctrlKey || e.metaKey) && isSaveKey) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }
    }

    window.addEventListener('keydown', blockSave, { capture: true });
    window.addEventListener('keypress', blockSave, { capture: true });
    document.addEventListener('keydown', blockSave, { capture: true });
    document.addEventListener('keypress', blockSave, { capture: true });
})();

document.addEventListener('DOMContentLoaded', function () {
    const dataEl = document.getElementById('masterListData');
    const el = document.getElementById('masterListSheet');
    if (!dataEl || !el) return;

    let rows = [];
    try {
        rows = JSON.parse(dataEl.textContent || '[]');
    } catch (e) {
        console.error('Failed to parse masterListData JSON', e);
        rows = [];
    }

    function detailsBtn(url, label) {
        if (!url) return '';
        return `<a href="${url}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary master-detail-btn">${label || 'Details'}</a>`;
    }

    const matrix = rows.map(r => ([
        detailsBtn(r.detail_url, r.detail_label),
        r.employee_code ?? '',
        r.family_name ?? '',
        r.first_name ?? '',
        r.nick_name ?? '',
        r.email ?? '',
        r.phone_number ?? '',
        r.address ?? '',
        r.employment_start_date ?? '',
        r.employment_end_date ?? '',
        r.employment_type_code ?? '',
        r.rest_pattern_name ?? '',
        r.employment_note ?? '',
        r.user_note ?? '',
        r.district_name ?? '',
        r.department_name ?? '',
        r.created_at ?? '',
        r.updated_at ?? '',
    ]));

    const heightSelect = document.getElementById('masterListHeight');
    const savedHeight = localStorage.getItem('masterListHeight') || '560';
    if (heightSelect) {
        heightSelect.value = savedHeight;
    }

    const topScroll = document.createElement('div');
    topScroll.className = 'master-list-top-scroll';
    const topScrollInner = document.createElement('div');
    topScrollInner.className = 'master-list-top-scroll-inner';
    topScroll.appendChild(topScrollInner);

    const scrollWrapper = document.createElement('div');
    scrollWrapper.className = 'master-list-scroll';
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

    applyTableHeight(savedHeight);
    heightSelect?.addEventListener('change', () => {
        localStorage.setItem('masterListHeight', heightSelect.value);
        applyTableHeight(heightSelect.value);
        window.requestAnimationFrame(syncTopScrollWidth);
    });

    topScroll.addEventListener('scroll', () => syncScrollLeft(topScroll, scrollWrapper));
    scrollWrapper.addEventListener('scroll', () => syncScrollLeft(scrollWrapper, topScroll));

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

    jspreadsheet(el, {
        worksheets: [{
            data: matrix,
            columns: [
                { title: 'Details', width: 100, readOnly: true, type: 'html' },
                { title: 'Employee Code', width: 130, readOnly: true },
                { title: 'Family Name', width: 140, readOnly: true },
                { title: 'First Name', width: 140, readOnly: true },
                { title: 'Nick Name', width: 140, readOnly: true },
                { title: 'Email', width: 240, readOnly: true },
                { title: 'Phone Number', width: 150, readOnly: true },
                { title: 'Address', width: 260, readOnly: true },
                { title: 'Start Date', width: 120, readOnly: true },
                { title: 'End Date', width: 120, readOnly: true },
                { title: 'Type Code', width: 120, readOnly: true },
                { title: 'Rest Pattern', width: 160, readOnly: true },
                { title: 'Employment Note', width: 240, readOnly: true },
                { title: 'User Note', width: 260, readOnly: true },
                { title: 'District', width: 160, readOnly: true },
                { title: 'Department', width: 160, readOnly: true },
                { title: 'Created At', width: 150, readOnly: true },
                { title: 'Last Updated', width: 150, readOnly: true },
            ],
            allowInsertRow: false,
            allowManualInsertRow: false,
            allowDeleteRow: false,
            allowInsertColumn: false,
            allowDeleteColumn: false,
            allowRenameColumn: false,
            allowComments: false,
            allowSaving: false,
            freezeColumns: 2,
            tableOverflow: false,
            tableHeight: '560px',
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
});
