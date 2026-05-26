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

    function detailsBtn(url) {
        if (!url) return '';
        return `<a href="${url}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Details</a>`;
    }

    const matrix = rows.map(r => ([
        detailsBtn(r.profile_url),
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
    ]));

    const scrollWrapper = document.createElement('div');
    scrollWrapper.style.cssText = 'width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;';
    el.parentNode.insertBefore(scrollWrapper, el);
    scrollWrapper.appendChild(el);

    jspreadsheet(el, {
        worksheets: [{
            data: matrix,
            columns: [
                { title: 'Details', width: 90, readOnly: true, type: 'html' },
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
});
