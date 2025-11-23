// public/js/routeDeclarationReport.js
document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('routeDeclarationData');
    const sheetContainer = document.getElementById('sheet');

    if (!dataEl || !sheetContainer) return;
    if (typeof jspreadsheet === 'undefined') {
        console.error('jspreadsheet is not loaded');
        return;
    }

    let rows = [];
    try {
        rows = JSON.parse(dataEl.textContent || '[]');
    } catch (e) {
        console.error('Failed to parse routeDeclarationData JSON', e);
        return;
    }

    const data = rows.map(r => [
        r.employee_code,
        r.display_name,
        r.status,
        r.submitted_at || '',
        r.effective_date || '',
        r.closest_station || '',
    ]);

    jspreadsheet(sheetContainer, {
        worksheets: [
            {
                data,
                columns: [
                    { type: 'text', title: 'Employee Code', width: 120, readOnly: true },
                    { type: 'text', title: 'Name', width: 220, readOnly: true },
                    { type: 'text', title: 'Status', width: 120, readOnly: true },
                    { type: 'text', title: 'Submitted At', width: 160, readOnly: true },
                    { type: 'text', title: 'Effective Date', width: 130, readOnly: true },
                    { type: 'text', title: 'Closest Station', width: 200, readOnly: true },
                ],
                columnDrag: false,
                allowInsertColumn: false,
                allowDeleteColumn: false,
                allowInsertRow: false,
                allowDeleteRow: false,
                editable: false,
                freezeColumns: 2,
                tableOverflow: false,
                tableHeight: '600px',
            },
        ],
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const block = document.getElementById('specificUserBlock');
    const radios = document.querySelectorAll('input[name="mode"]');

    if (!block || !radios.length) return;

    const updateSpecificUserVisibility = () => {
        const checked = document.querySelector('input[name="mode"]:checked');
        const isUserMode = checked && checked.value === 'user';
        block.style.display = isUserMode ? '' : 'none';
    };

    radios.forEach(r => {
        r.addEventListener('change', updateSpecificUserVisibility);
    });

    // 初期表示の反映（リロード時）
    updateSpecificUserVisibility();
});
