document.addEventListener('DOMContentLoaded', () => {
    // --- First initialization block ---
    const dataEl = document.getElementById('routeDeclarationData');
    const sheetContainer = document.getElementById('sheet');
    if (dataEl && sheetContainer) {
        if (typeof jspreadsheet === 'undefined') {
            console.error('jspreadsheet is not loaded');
        } else {
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
            // スマホ横スクロール：jSpreadsheet の外側をラップ
            const scrollWrapper = document.createElement('div');
            scrollWrapper.style.cssText = 'width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;';
            sheetContainer.parentNode.insertBefore(scrollWrapper, sheetContainer);
            scrollWrapper.appendChild(sheetContainer);

            jspreadsheet(sheetContainer, {
                worksheets: [
                    {
                        data,
                        columns: [
                            { type: 'text', title: 'Employee Code', width: 120, readOnly: true },
                            { type: 'text', title: 'Name', width: 291, readOnly: true },
                            { type: 'text', title: 'Status', width: 170, readOnly: true },
                            { type: 'text', title: 'Submitted At', width: 170, readOnly: true },
                            { type: 'text', title: 'Effective Date', width: 170, readOnly: true },
                            { type: 'text', title: 'Closest Station', width: 320, readOnly: true },
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
        }
    }
    // --- Second initialization block ---
    const block = document.getElementById('specificUserBlock');
    const radios = document.querySelectorAll('input[name="mode"]');
    if (block && radios.length) {
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
    }
});
