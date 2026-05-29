(function () {
  'use strict';

  function notify(message, variant = 'success') {
    if (typeof window.showToast === 'function') {
      window.showToast(message, { variant, delay: 9000 });
      return;
    }
    window.alert(message);
  }

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

  document.addEventListener('DOMContentLoaded', function () {
    const boot = window.COMMUTE_PATTERN_BOOTSTRAP || {};
    const sheetEl = document.getElementById('patternSheet');
    const scrollWrapper = document.getElementById('patternSheetScroll');

    if (!sheetEl || !scrollWrapper) return;

    const csrfToken = boot.csrfToken;
    const patternId = boot.patternId || null;
    const targetUserId = boot.targetUserId;
    const initialRows = boot.initialRows || [];
    const dowValues = boot.dowValues || ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const dowOrder = new Map(dowValues.map((dow, index) => [dow, index]));

    const ACTION_BTN_HTML = '<button type="button" class="btn btn-outline-danger btn-sm js-row-del" title="この行を削除">Del</button>';
    const ADD_BTN_HTML = '<button type="button" class="btn btn-outline-primary btn-sm js-row-add" title="この曜日の行を下に追加">Add</button>';

    const COL = Object.freeze({
      ACTIONS: 0,
      ADD: 1,
      DAY: 2,
      FROM: 3,
      TO: 4,
      AMOUNT: 5,
      TRIP: 6,
      NOTE: 7,
      ID: 8,
      SEQ: 9,
    });

    const dayOptions = dowValues.map(dow => ({ id: dow, name: dow }));
    const tripTypeOptions = [
      { id: 'round_trip', name: 'Round Trip' },
      { id: 'one_way', name: 'One Way' },
    ];

    function buildMatrix(rows) {
      return rows.map(r => [
        ACTION_BTN_HTML,
        ADD_BTN_HTML,
        r.dow || 'Sun',
        r.station_from || '',
        r.station_to || '',
        Number.isFinite(Number(r.cost)) ? Number(r.cost) : 0,
        r.trip_type || 'round_trip',
        r.note || '',
        r.id ?? '',
        r.seq ?? 100,
      ]);
    }

    const heightSelect = document.getElementById('commutePatternHeight');
    const savedHeight = localStorage.getItem('commutePatternHeight') || '560';

    if (heightSelect) {
      heightSelect.value = savedHeight;
    }

    function applyTableHeight(value) {
      if (value === 'full') {
        scrollWrapper.style.maxHeight = '';
      } else {
        scrollWrapper.style.maxHeight = `${Number(value) || 560}px`;
      }
    }

    applyTableHeight(savedHeight);
    heightSelect?.addEventListener('change', () => {
      localStorage.setItem('commutePatternHeight', heightSelect.value);
      applyTableHeight(heightSelect.value);
    });

    const sheet = jspreadsheet(sheetEl, {
      worksheets: [{
        data: buildMatrix(initialRows),
        columns: [
          { title: '-', type: 'html', width: 60, readOnly: true },
          { title: '+', type: 'html', width: 60, readOnly: true },
          { title: 'Day', type: 'dropdown', width: 90, source: dayOptions },
          { title: 'From', type: 'text', width: 220 },
          { title: 'To', type: 'text', width: 220 },
          { title: 'Amount', type: 'numeric', width: 110, mask: '#,##0' },
          { title: 'Trip Type', type: 'dropdown', width: 120, source: tripTypeOptions },
          { title: 'Note', type: 'text', width: 260 },
          { title: '_id', type: 'text', width: 0, readOnly: true },
          { title: '_seq', type: 'numeric', width: 0, readOnly: true },
        ],
        minDimensions: [10, Math.max(initialRows.length, 1)],
        allowInsertRow: false,
        allowManualInsertRow: false,
        allowDeleteRow: false,
        allowInsertColumn: false,
        allowDeleteColumn: false,
        allowRenameColumn: false,
        allowComments: false,
        allowSaving: false,
        tableOverflow: false,
        tableHeight: '470px',
      }],
    });

    function hideInternalCols() {
      sheet[0].hideColumn(COL.ID);
      sheet[0].hideColumn(COL.SEQ);
    }
    hideInternalCols();

    function readCurrentRows() {
      const data = sheet[0].getData(false);
      return data.map(arr => ({
        dow: arr[COL.DAY] || '',
        station_from: arr[COL.FROM] || '',
        station_to: arr[COL.TO] || '',
        cost: (arr[COL.AMOUNT] === '' || arr[COL.AMOUNT] == null)
          ? 0
          : Number(String(arr[COL.AMOUNT]).replace(/,/g, '')),
        trip_type: arr[COL.TRIP] || '',
        note: arr[COL.NOTE] || '',
        id: arr[COL.ID] || '',
        seq: (arr[COL.SEQ] === '' || arr[COL.SEQ] == null) ? 100 : Number(arr[COL.SEQ]),
      })).filter(r => r.dow);
    }

    function sortRows(rows) {
      return rows.slice().sort((a, b) => {
        const aDow = dowOrder.has(a.dow) ? dowOrder.get(a.dow) : 99;
        const bDow = dowOrder.has(b.dow) ? dowOrder.get(b.dow) : 99;
        if (aDow !== bDow) return aDow - bDow;
        return (Number(a.seq) || 0) - (Number(b.seq) || 0);
      });
    }

    function renderRows(rows) {
      sheet[0].setData(buildMatrix(sortRows(rows)));
      hideInternalCols();
    }

    function decideSeqForDay(targetDow, rows, hintAfterSeq = null) {
      const same = rows.filter(r => r.dow === targetDow).sort((a, b) => a.seq - b.seq);
      if (same.length === 0) return 100;
      const maxSeq = same[same.length - 1].seq ?? 100;
      if (hintAfterSeq == null) return maxSeq + 100;

      const next = same.find(r => r.seq > hintAfterSeq);
      if (next && (next.seq - hintAfterSeq) > 1) return Math.floor((hintAfterSeq + next.seq) / 2);
      return maxSeq + 100;
    }

    const pickDowEl = document.getElementById('pickDow');
    const addDowBtn = document.getElementById('addDowBtn');

    addDowBtn?.addEventListener('click', () => {
      const dow = pickDowEl?.value || 'Sun';
      const rows = readCurrentRows();
      const seq = decideSeqForDay(dow, rows);
      rows.push({
        dow,
        station_from: '',
        station_to: '',
        cost: 0,
        trip_type: 'round_trip',
        note: '',
        id: '',
        seq,
      });
      const updated = sortRows(rows);
      renderRows(updated);
      const newIndex = updated.findIndex(r => r.dow === dow && r.seq === seq);
      if (newIndex >= 0) sheet[0].selectCell(COL.FROM, newIndex);
      notify(`Row added for ${dow}.`, 'success');
    });

    sheetEl.addEventListener('click', (e) => {
      const delBtn = e.target.closest('.js-row-del');
      const addBtn = e.target.closest('.js-row-add');
      const td = e.target.closest('td');
      if (!td) return;

      const rowIndex = Number(td.getAttribute('data-y'));
      if (Number.isNaN(rowIndex) || rowIndex < 0) return;

      if (delBtn) {
        const rows = readCurrentRows();
        if (rows.length <= 1) {
          const only = rows[0] || { dow: 'Sun', seq: 100 };
          renderRows([{
            ...only,
            station_from: '',
            station_to: '',
            cost: 0,
            trip_type: 'round_trip',
            note: '',
            id: only.id || '',
          }]);
          notify('The last row has been cleared.', 'warning');
          return;
        }

        const deleted = rows[rowIndex];
        rows.splice(rowIndex, 1);
        renderRows(rows);
        notify(`Row for ${deleted?.dow || 'selected day'} will be deleted on save.`, 'success');
        return;
      }

      if (addBtn) {
        const rows = readCurrentRows();
        const row = rows[rowIndex];
        if (!row) return;

        const seq = decideSeqForDay(row.dow, rows, row.seq);
        rows.splice(rowIndex + 1, 0, {
          dow: row.dow,
          station_from: '',
          station_to: '',
          cost: 0,
          trip_type: 'round_trip',
          note: '',
          id: '',
          seq,
        });

        const updated = sortRows(rows);
        renderRows(updated);
        const newIndex = updated.findIndex(r => r.dow === row.dow && r.seq === seq);
        if (newIndex >= 0) sheet[0].selectCell(COL.FROM, newIndex);
        notify(`Row added for ${row.dow}.`, 'success');
      }
    });

    const validFromEl = document.getElementById('validFrom');
    const validToEl = document.getElementById('validTo');
    let validToTouched = false;

    function defaultValidTo(validFrom) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(validFrom || '')) return '';
      const [year, month] = validFrom.split('-').map(Number);
      const endYear = month <= 3 ? year : year + 1;
      return `${String(endYear).padStart(4, '0')}-03-31`;
    }

    validToEl?.addEventListener('input', () => {
      validToTouched = true;
    });

    validFromEl?.addEventListener('change', () => {
      if (!validToEl || validToTouched) return;
      validToEl.value = defaultValidTo(validFromEl.value);
    });

    function metaPayload() {
      return {
        pattern_id: patternId,
        user_id: targetUserId,
        closest_station: document.getElementById('closestStation')?.value?.trim() || '',
        train_line: document.getElementById('trainLine')?.value?.trim() || null,
        valid_from: validFromEl?.value || '',
        valid_to: validToEl?.value || '',
        reason: document.getElementById('patternReason')?.value?.trim() || null,
      };
    }

    function validateBeforeSave(meta, rows) {
      if (!meta.closest_station) return 'Closest Station is required.';
      if (!meta.valid_from || !meta.valid_to) return 'Valid From and Valid To are required.';
      if (meta.valid_to < meta.valid_from) return 'Valid To must be after or equal to Valid From.';
      if (!rows.length) return 'At least one pattern row is required.';

      for (const row of rows) {
        if (!dowOrder.has(row.dow)) return `Invalid day: ${row.dow}`;
        if (!Number.isFinite(row.cost) || row.cost < 0) return `Invalid amount: ${row.cost}`;
        if (!row.trip_type) return `Please select Trip Type for ${row.dow}.`;
      }

      return null;
    }

    const saveBtn = document.getElementById('savePatternBtn');
    saveBtn?.addEventListener('click', async () => {
      const meta = metaPayload();
      const rows = sortRows(readCurrentRows());
      const validationError = validateBeforeSave(meta, rows);
      if (validationError) {
        notify(validationError, 'warning');
        return;
      }

      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';

      try {
        const resp = await fetch(boot.saveUrl, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ ...meta, rows }),
        });

        if (!resp.ok) {
          let msg = '';
          try { msg = (await resp.json())?.message || ''; } catch (_) { msg = await resp.text(); }
          throw new Error(`Save failed: ${resp.status} ${msg}`);
        }

        const data = await resp.json();
        notify('Saved successfully.', 'success');
        setTimeout(() => {
          window.location.href = data.redirect_url || window.location.href;
        }, 900);
      } catch (err) {
        console.error(err);
        notify('An error occurred while saving. ' + (err?.message || err), 'danger');
      } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save';
      }
    });

    const deletePatternBtn = document.getElementById('deletePatternBtn');
    deletePatternBtn?.addEventListener('click', () => {
      if (!boot.deleteUrl) return;

      const modalEl = document.getElementById('confirmPatternDeleteModal');
      const modal = new bootstrap.Modal(modalEl);
      modal.show();

      const yesBtn = document.getElementById('confirmPatternDeleteYes');
      yesBtn.replaceWith(yesBtn.cloneNode(true));
      const newYesBtn = document.getElementById('confirmPatternDeleteYes');

      newYesBtn.addEventListener('click', async () => {
        modal.hide();
        deletePatternBtn.disabled = true;

        try {
          const resp = await fetch(boot.deleteUrl, {
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
          });

          if (!resp.ok) {
            let msg = '';
            try { msg = (await resp.json())?.message || ''; } catch (_) { msg = await resp.text(); }
            throw new Error(`Delete failed: ${resp.status} ${msg}`);
          }

          const data = await resp.json();
          notify('Deleted successfully.', 'success');
          setTimeout(() => {
            window.location.href = data.redirect_url || window.location.href;
          }, 900);
        } catch (err) {
          console.error(err);
          notify('Deletion error: ' + (err?.message || err), 'danger');
          deletePatternBtn.disabled = false;
        }
      });
    });

    (function setupFloatingSave() {
      if (!saveBtn) return;

      const bgBar = document.createElement('div');
      bgBar.id = 'patternSaveBtnBackground';
      bgBar.style.position = 'fixed';
      bgBar.style.left = '0';
      bgBar.style.right = '0';
      bgBar.style.bottom = '0';
      bgBar.style.height = '60px';
      bgBar.style.background = 'rgba(136, 174, 255, 0.18)';
      bgBar.style.backdropFilter = 'blur(1px)';
      bgBar.style.zIndex = '998';
      bgBar.style.display = 'none';
      document.body.appendChild(bgBar);

      const floatingBtn = saveBtn.cloneNode(true);
      floatingBtn.id = 'savePatternBtnFloating';
      floatingBtn.style.position = 'fixed';
      floatingBtn.style.bottom = '14px';
      floatingBtn.style.right = '20px';
      floatingBtn.style.zIndex = '999';
      floatingBtn.style.display = 'none';
      floatingBtn.style.boxShadow = '0 2px 8px rgba(0,0,0,0.25)';
      floatingBtn.style.opacity = '0.95';
      floatingBtn.style.borderRadius = '6px';
      document.body.appendChild(floatingBtn);

      floatingBtn.addEventListener('click', () => saveBtn.click());

      function checkSaveBtnVisibility() {
        const rect = saveBtn.getBoundingClientRect();
        const inView = rect.top >= 0 && rect.bottom <= window.innerHeight;
        floatingBtn.style.display = inView ? 'none' : 'block';
        bgBar.style.display = inView ? 'none' : 'block';
      }

      window.addEventListener('scroll', checkSaveBtnVisibility);
      window.addEventListener('resize', checkSaveBtnVisibility);
      checkSaveBtnVisibility();
    })();
  });
})();
