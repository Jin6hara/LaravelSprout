(function () {
  'use strict';

  // ====== 1) 月検索（元の短いスクリプト） ======
  document.addEventListener('DOMContentLoaded', function () {
    const monthInput = document.getElementById('monthPick');
    const btn = document.getElementById('monthSearchBtn');

    function doSearch() {
      const v = monthInput?.value || '';
      if (!/^\d{4}-\d{2}$/.test(v)) { alert('対象月を選択してください。'); return; }
      const [yy, mm] = v.split('-');
      const url = new URL(window.location.href);
      url.searchParams.set('year', yy);
      url.searchParams.set('month', Number(mm));
      window.location = url.toString();
    }
    btn?.addEventListener('click', (e) => { e.preventDefault(); doSearch(); });
    monthInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });
  });

  // ====== 2) JSpreadsheet 初期化以降（大部分） ======
  document.addEventListener('DOMContentLoaded', function () {
    const boot = window.EXPENSES_BOOTSTRAP || {};
    if (!boot.hasReport) return; // レポート無い月は何もしない

    const csrfToken = boot.csrfToken;
    const reportId = boot.reportId;
    const year = boot.year;
    const month = boot.month;
    const initialRows = boot.initialRows || [];
    const eventOnMap = boot.eventOnMap || {};
    const passActiveMap = boot.passActiveMap || {};

    // 色定数
    const COLOR_WORK_ON = '#6b92ff';
    const COLOR_WORK_OFF = '#e68484';
    const COLOR_PASS_ON = '#9bf59b';
    const COLOR_PASS_OFF = '#ffffff';

    // 操作ボタンHTML
    const ACTION_BTN_HTML = '<button type="button" class="btn btn-outline-danger btn-sm js-row-del" title="この行を削除">Del</button>';
    const ADD_BTN_HTML = '<button type="button" class="btn btn-outline-primary btn-sm js-row-add" title="この日の行を下に追加">Add</button>';

    // 列定数
    const COL = Object.freeze({
      ACTIONS: 0, ADD: 1, DATE: 2, DAY: 3, WORK: 4,
      FROM: 5, TO: 6, AMOUNT: 7, TRIP: 8, NOTE: 9,
      ID: 10, SEQ: 11, PASS: 12,
    });

    function enWeekday(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr + 'T00:00:00');
      if (isNaN(d)) return '';
      return ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][d.getDay()];
    }

    const tripTypeOptions = [
      { id: 'round_trip', name: 'Round Trip' },
      { id: 'one_way', name: 'One Way' },
    ];

    // 初期マトリクス
    const matrix = initialRows.map(r => {
      const date = r.expense_date || '';
      const isEventOn = !!eventOnMap[date];
      const isPassOn = !!passActiveMap[date];
      const colorWork = isEventOn ? COLOR_WORK_ON : COLOR_WORK_OFF;
      const colorPass = isPassOn ? COLOR_PASS_ON : COLOR_PASS_OFF;
      return [
        ACTION_BTN_HTML, ADD_BTN_HTML,
        date, enWeekday(date),
        colorWork,
        r.station_from || '', r.station_to || '',
        Number.isFinite(r.cost) ? r.cost : 0,
        r.trip_type || '',
        r.note || '',
        r.id ?? '',
        (r.seq ?? 100),
        colorPass,
      ];
    });

    // シート生成
    const sheet = jspreadsheet(document.getElementById('sheet'), {
      worksheets: [{
        data: matrix,
        columns: [
          { title: '-', type: 'html', width: 50, readOnly: true },
          { title: '+', type: 'html', width: 50, readOnly: true },
          { title: 'Date', type: 'text', width: 107, readOnly: true },
          { title: 'Day', type: 'text', width: 65, readOnly: true },
          { title: 'Work', type: 'color', width: 65, render: 'square', readOnly: true },
          { title: 'From', type: 'text', width: 200 },
          { title: 'To', type: 'text', width: 200 },
          { title: 'Amount', type: 'numeric', width: 100, mask: '#,##0' },
          { title: 'Trip Type', type: 'dropdown', width: 100, source: tripTypeOptions },
          { title: 'Note', type: 'text', width: 240 },
          { title: '_id', type: 'text', width: 0, readOnly: true },
          { title: '_seq', type: 'numeric', width: 0, readOnly: true },
          { title: 'Pass', type: 'color', width: 65, render: 'square', readOnly: true },
        ],
        minDimensions: [13, Math.max(matrix.length, 1)],
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
        onselection: function (el, column, row) {
          lastSelectedRow = (typeof row === 'number') ? row : null;
        }
      }]
    });

    // 隠し列
    function hideInternalCols() {
      sheet[0].hideColumn(COL.ID);
      sheet[0].hideColumn(COL.SEQ);
    }
    hideInternalCols();

    // 現在行の読取
    function readCurrentRows() {
      const data = sheet[0].getData(false);
      return data.map(arr => ({
        date: arr[COL.DATE] || '',
        day: arr[COL.DAY] || '',
        colorWork: arr[COL.WORK] || COLOR_WORK_OFF,
        from: arr[COL.FROM] || '',
        to: arr[COL.TO] || '',
        cost: (arr[COL.AMOUNT] === '' || arr[COL.AMOUNT] == null) ? 0 : Number(String(arr[COL.AMOUNT]).replace(/,/g, '')),
        trip: arr[COL.TRIP] || '',
        note: arr[COL.NOTE] || '',
        id: arr[COL.ID] || '',
        seq: (arr[COL.SEQ] === '' || arr[COL.SEQ] == null) ? 100 : Number(arr[COL.SEQ]),
        colorPass: arr[COL.PASS] || COLOR_PASS_OFF,
      })).filter(r => r.date);
    }

    function sortRowsByDateThenSeq(rows) {
      return rows.slice().sort((a, b) => {
        if (a.date < b.date) return -1;
        if (a.date > b.date) return 1;
        return (a.seq - b.seq);
      });
    }

    function renderRows(rows) {
      const newMatrix = rows.map(r => {
        const work = eventOnMap[r.date] ? COLOR_WORK_ON : COLOR_WORK_OFF;
        const pass = passActiveMap[r.date] ? COLOR_PASS_ON : COLOR_PASS_OFF;
        return [
          ACTION_BTN_HTML, ADD_BTN_HTML,
          r.date, enWeekday(r.date),
          work,
          r.from || '', r.to || '',
          r.cost || 0,
          r.trip || '',
          r.note || '',
          r.id || '',
          r.seq ?? 100,
          pass,
        ];
      });
      sheet[0].setData(newMatrix);
      hideInternalCols();
    }

    const initialIdSet = new Set((initialRows || []).map(r => String(r.id)));

    // 指定日追加
    const pickDateEl = document.getElementById('pickDate');
    const addByDateBtn = document.getElementById('addByDateBtn');
    let lastSelectedRow = null;

    function isValidDateStr(yyyy_mm_dd) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(yyyy_mm_dd)) return false;
      const [yy, mm, dd] = yyyy_mm_dd.split('-').map(Number);
      const d = new Date(yyyy_mm_dd + 'T00:00:00');
      if (isNaN(d)) return false;
      return (yy === Number(year) && mm === Number(month) && dd >= 1 && dd <= 31);
    }

    function enableAddButtonIfValid() {
      const v = pickDateEl?.value || '';
      addByDateBtn && (addByDateBtn.disabled = !isValidDateStr(v));
    }
    pickDateEl?.addEventListener('input', enableAddButtonIfValid);
    enableAddButtonIfValid();

    function decideSeqForDate(targetDate, rows, hintAfterSeq = null) {
      const same = rows.filter(r => r.date === targetDate).sort((a, b) => a.seq - b.seq);
      if (same.length === 0) return 1024;
      const maxSeq = same[same.length - 1].seq ?? 100;
      if (hintAfterSeq == null) return maxSeq + 1024;

      const next = same.find(r => r.seq > hintAfterSeq);
      if (next && (next.seq - hintAfterSeq) > 1) {
        return Math.floor((hintAfterSeq + next.seq) / 2);
      }
      return maxSeq + 1024;
    }

    addByDateBtn?.addEventListener('click', () => {
      const dateStr = pickDateEl?.value || '';
      if (!isValidDateStr(dateStr)) { alert('この月内の日付を選択してください。'); return; }

      const rows = readCurrentRows();
      let hintAfterSeq = null;
      if (lastSelectedRow != null) {
        const selected = rows[lastSelectedRow];
        if (selected && selected.date === dateStr) hintAfterSeq = selected.seq ?? null;
      }
      const newSeq = decideSeqForDate(dateStr, rows, hintAfterSeq);

      const newRow = { date: dateStr, day: enWeekday(dateStr), from: '', to: '', cost: 0, trip: '', note: '', id: '', seq: newSeq };
      const updated = sortRowsByDateThenSeq([...rows, newRow]);
      renderRows(updated);
      enableAddButtonIfValid();
    });

    // 保存
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
      saveBtn.addEventListener('click', async () => {
        const rows = readCurrentRows();

        for (const r of rows) {
          if (!/^\d{4}-\d{2}-\d{2}$/.test(r.date)) { alert(`日付形式エラー: ${r.date}`); return; }
          const [yy, mm] = r.date.split('-').map(Number);
          if (yy !== Number(year) || mm !== Number(month)) { alert(`この月以外の日付が含まれています: ${r.date}`); return; }
          if (r.cost < 0 || !Number.isFinite(r.cost)) { alert(`金額が不正です: ${r.cost}`); return; }
          if (!r.trip) { alert(`Trip Type が未選択の日があります: ${r.date}`); return; }
        }

        saveBtn.disabled = true; saveBtn.textContent = '保存中…';
        try {
          const updates = rows.filter(r => r.id && initialIdSet.has(String(r.id)));
          for (const u of updates) {
            const resp = await fetch(`/api/expenses/${u.id}`, {
              method: 'PUT',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
              body: JSON.stringify({
                station_from: u.from || null,
                station_to: u.to || null,
                note: u.note || null,
                cost: u.cost,
                trip_type: u.trip,
                seq: u.seq,
              }),
            });
            if (!resp.ok) throw new Error(`更新失敗 (ID:${u.id}): ${resp.status} ${await resp.text()}`);
          }

          const creates = rows.filter(r => !r.id);
          for (const c of creates) {
            const seq = Number.isFinite(c.seq) ? c.seq : 100;
            const resp = await fetch('/api/expenses', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
              body: JSON.stringify({
                expense_report_id: reportId,
                expense_date: c.date,
                seq: seq,
                station_from: c.from || null,
                station_to: c.to || null,
                note: c.note || null,
                cost: c.cost,
                trip_type: c.trip,
                category: 'regular',
              }),
            });
            if (!resp.ok) throw new Error(`作成失敗 (Date:${c.date}): ${resp.status} ${await resp.text()}`);
          }

          alert('保存しました。');
          location.reload();

        } catch (err) {
          console.error(err);
          alert('保存でエラーが発生しました。\n' + (err?.message || err));
        } finally {
          saveBtn.disabled = false; saveBtn.textContent = '保存';
        }
      });
    }

    // クリック委譲（削除と追加）
    const sheetEl = document.getElementById('sheet');
    sheetEl.addEventListener('click', async (e) => {
      const delBtn = e.target.closest('.js-row-del');
      const addBtn = e.target.closest('.js-row-add');
      const td = e.target.closest('td');
      if (!td) return;
      const rowIndex = Number(td.getAttribute('data-y'));
      if (Number.isNaN(rowIndex) || rowIndex < 0) return;

      // 削除
      if (delBtn) {
        const rowData = sheet[0].getRowData(rowIndex);
        const id = rowData[COL.ID];
        if (!id) { sheet[0].deleteRow(rowIndex); return; }
        if (!confirm('この行を削除します。よろしいですか？')) return;
        try {
          const resp = await fetch(`/api/expenses/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
          });
          if (!resp.ok) throw new Error(`削除失敗 (ID:${id}): ${resp.status} ${await resp.text()}`);
          sheet[0].deleteRow(rowIndex);
        } catch (err) {
          console.error(err);
          alert('削除エラー: ' + (err?.message || err));
        }
        return;
      }

      // 追加（同日、直後）
      if (addBtn) {
        const rows = readCurrentRows();
        const rowArr = sheet[0].getRowData(rowIndex);
        const date = rowArr[COL.DATE];
        const curSeq = Number(rowArr[COL.SEQ] ?? 100);

        const same = rows.filter(r => r.date === date).sort((a, b) => a.seq - b.seq);
        const maxSeq = same.length ? same[same.length - 1].seq : 100;

        let newSeq;
        if (same.length === 1 || curSeq === maxSeq) {
          newSeq = maxSeq + 1024;
        } else {
          const next = same.find(r => r.seq > curSeq);
          if (next && (next.seq - curSeq) > 1) newSeq = Math.floor((curSeq + next.seq) / 2);
          else newSeq = maxSeq + 1024;
        }

        const newRow = { date, day: enWeekday(date), from: '', to: '', cost: 0, trip: '', note: '', id: '', seq: newSeq };
        const rowsWithNew = [...rows.slice(0, rowIndex + 1), newRow, ...rows.slice(rowIndex + 1)];
        const updated = rowsWithNew.slice().sort((a, b) => (a.date === b.date) ? (a.seq - b.seq) : (a.date < b.date ? -1 : 1));
        renderRows(updated);

        const newIndex = updated.findIndex(r => r.date === date && r.seq === newSeq);
        if (newIndex >= 0) sheet[0].selectCell(COL.FROM, newIndex);
      }
    });
  });
  // ====== 3) フォーム横スクロール対応（画面幅1300px以下時） ======
  const formEl = document.querySelector('form');
  if (formEl) {
    function updateFormScrollStyle() {
      if (window.innerWidth <= 1300) {
        formEl.style.overflowX = 'auto';
        formEl.style.display = 'block';
        formEl.style.paddingBottom = '8px'; // スクロールバー分の余白
      } else {
        formEl.style.overflowX = '';
        formEl.style.display = '';
        formEl.style.paddingBottom = '';
      }
    }

    // 初期とリサイズ時に適用
    updateFormScrollStyle();
    window.addEventListener('resize', updateFormScrollStyle);
  }

  // ====== 4) 保存ボタン追従（画面下固定） ======
  document.addEventListener('DOMContentLoaded', function () {
    const saveBtn = document.getElementById('saveBtn');
    if (!saveBtn) return;

    // ▼ 背景帯を作成（固定位置、半透明＋ぼかし）
    const bgBar = document.createElement('div');
    bgBar.id = 'saveBtnBackground';
    bgBar.style.position = 'fixed';
    bgBar.style.left = '0';
    bgBar.style.right = '0';
    bgBar.style.bottom = '0';
    bgBar.style.height = '60px';
    bgBar.style.background = 'rgba(123, 196, 255, 0.18)';
    bgBar.style.backdropFilter = 'blur(0px)';
    bgBar.style.zIndex = '998';
    bgBar.style.display = 'none';
    document.body.appendChild(bgBar);

    // ▼ 浮動ボタンを複製して上に置く
    const floatingBtn = saveBtn.cloneNode(true);
    floatingBtn.id = 'saveBtnFloating';
    floatingBtn.style.position = 'fixed';
    floatingBtn.style.bottom = '14px';
    floatingBtn.style.right = '20px';
    floatingBtn.style.zIndex = '999';
    floatingBtn.style.display = 'none';
    floatingBtn.style.boxShadow = '0 2px 8px rgba(0,0,0,0.25)';
    floatingBtn.style.opacity = '0.95';
    floatingBtn.style.borderRadius = '6px';
    document.body.appendChild(floatingBtn);

    // 同じ動作を連動
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
  });
})();
