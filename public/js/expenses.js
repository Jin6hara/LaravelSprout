(function () {
  'use strict';

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

  // ====== 1) 月検索（フォームだけ横スクロールを付与） ======
  document.addEventListener('DOMContentLoaded', function () {
    const monthInput = document.getElementById('monthPick');
    const btn = document.getElementById('monthSearchBtn');
    const monthForm = document.getElementById('monthForm');

    function doSearch() {
      const v = monthInput?.value || '';
      if (!/^\d{4}-\d{2}$/.test(v)) { showToast('対象月を選択してください。', 'warning'); return; }
      const [yy, mm] = v.split('-');
      const url = new URL(window.location.href);
      url.searchParams.set('year', yy);
      url.searchParams.set('month', Number(mm));
      window.location = url.toString();
    }
    btn?.addEventListener('click', (e) => { e.preventDefault(); doSearch(); });
    monthInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });

    // ▼ 画面幅1300px以下のとき、フォームにだけ下部横スクロール
    function updateFormScrollStyle() {
      if (!monthForm) return;
      if (window.innerWidth <= 1300) {
        monthForm.style.overflowX = 'auto';
        monthForm.style.display = 'block';
        monthForm.style.paddingBottom = '8px'; // スクロールバー分の余白
      } else {
        monthForm.style.overflowX = '';
        monthForm.style.display = '';
        monthForm.style.paddingBottom = '';
      }
    }
    updateFormScrollStyle();
    window.addEventListener('resize', updateFormScrollStyle);
  });

  // ====== 2) JSpreadsheet 初期化以降（編集画面の主要ロジック） ======
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
    const isLocked = !!boot.isLocked; // ★ 提出済みロック

    // 色定数
    const COLOR_WORK_ON = '#6b92ff';
    const COLOR_WORK_OFF = '#e68484';
    const COLOR_PASS_ON = '#9bf59b';
    const COLOR_PASS_OFF = '#ffffff';

    // 操作ボタンHTML（ロック中は生成しない）
    const ACTION_BTN_HTML = isLocked ? '' : '<button type="button" class="btn btn-outline-danger btn-sm js-row-del" title="この行を削除">Del</button>';
    const ADD_BTN_HTML = isLocked ? '' : '<button type="button" class="btn btn-outline-primary btn-sm js-row-add" title="この日の行を下に追加">Add</button>';

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

    // 合計表示の更新
    function updateTotal(rows) {
      const total = rows.reduce((sum, r) => sum + (Number(r.cost) || 0), 0);
      const el = document.getElementById('sumCost');
      if (el) el.textContent = total.toLocaleString();
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
          { title: 'From', type: 'text', width: 200, readOnly: isLocked }, // ★ ロックで編集不可
          { title: 'To', type: 'text', width: 200, readOnly: isLocked },
          { title: 'Amount', type: 'numeric', width: 100, mask: '#,##0', readOnly: isLocked },
          { title: 'Trip Type', type: 'dropdown', width: 100, source: tripTypeOptions, readOnly: isLocked },
          { title: 'Note', type: 'text', width: 240, readOnly: isLocked },
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
      addByDateBtn && (addByDateBtn.disabled = !isValidDateStr(v) || isLocked);
    }
    pickDateEl?.addEventListener('input', enableAddButtonIfValid);
    enableAddButtonIfValid();

    function decideSeqForDate(targetDate, rows, hintAfterSeq = null) {
      const same = rows.filter(r => r.date === targetDate).sort((a, b) => a.seq - b.seq);
      if (same.length === 0) return 1024;
      const maxSeq = same[same.length - 1].seq ?? 100;
      if (hintAfterSeq == null) return maxSeq + 1024;

      const next = same.find(r => r.seq > hintAfterSeq);
      if (next && (next.seq - hintAfterSeq) > 1) return Math.floor((hintAfterSeq + next.seq) / 2);
      return maxSeq + 1024;
    }

    addByDateBtn?.addEventListener('click', () => {
      if (isLocked) return; // ★ ロック中は何もしない
      const dateStr = pickDateEl?.value || '';
      if (!isValidDateStr(dateStr)) { showToast('この月内の日付を選択してください。', 'warning'); return; }

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
      // ロック中はボタン自体を無効化
      if (isLocked) saveBtn.disabled = true;

      saveBtn.addEventListener('click', async () => {
        if (isLocked) return; // ★ ロックなら何もしない

        const rows = readCurrentRows();

        for (const r of rows) {
          if (!/^\d{4}-\d{2}-\d{2}$/.test(r.date)) { showToast(`日付形式エラー: ${r.date}`, 'warning'); return; }
          const [yy, mm] = r.date.split('-').map(Number);
          if (yy !== Number(year) || mm !== Number(month)) { showToast(`この月以外の日付が含まれています: ${r.date}`, 'warning'); return; }
          if (r.cost < 0 || !Number.isFinite(r.cost)) { showToast(`金額が不正です: ${r.cost}`, 'warning'); return; }
          if (!r.trip) { showToast(`Trip Type が未選択の日があります: ${r.date}`, 'warning'); return; }
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

          showToast('保存しました。', 'success');
          location.reload();

        } catch (err) {
          console.error(err);
          showToast('保存でエラーが発生しました。\n' + (err?.message || err), 'danger');
        } finally {
          saveBtn.disabled = false; saveBtn.textContent = '保存';
        }
      });
    }

    // クリック委譲（削除と追加）
    const sheetEl = document.getElementById('sheet');
    sheetEl.addEventListener('click', async (e) => {
      if (isLocked) return; // ★ ロック時はセル内ボタン無効

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
        const isUnsaved = !id;

        // --- モーダル表示 ---
        const modalEl = document.getElementById('confirmDeleteModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        // イベント重複防止
        const yesBtn = document.getElementById('confirmDeleteYes');
        yesBtn.replaceWith(yesBtn.cloneNode(true));
        const newYesBtn = document.getElementById('confirmDeleteYes');

        newYesBtn.addEventListener('click', async () => {
          modal.hide();

          // 未保存行はクライアント側だけで削除
          if (isUnsaved) {
            sheet[0].deleteRow(rowIndex);
            const rowsAfter = readCurrentRows();
            renderRows(rowsAfter);
            updateTotal(rowsAfter);
            showToast('未保存の行を削除しました。', 'success');
            return;
          }

          // サーバ削除
          try {
            const resp = await fetch(`/api/expenses/${id}`, {
              method: 'DELETE',
              headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });

            if (!resp.ok) {
              // --- 423などのエラーをきれいに扱う ---
              let apiMsg = '';
              try {
                const data = await resp.json();
                apiMsg = data?.message || '';
              } catch (_) {
                const t = await resp.text();
                apiMsg = (t || '').slice(0, 200);
              }

              if (resp.status === 423) {
                showToast('提出済みのため削除できません（ロック中）。', 'warning');
                console.warn('Delete locked (423):', apiMsg);
                return;
              }

              console.error('Delete failed:', resp.status, apiMsg);
              showToast(`削除に失敗しました（${resp.status}）。${apiMsg}`, 'danger');
              return;
            }

            // --- 成功時（DOMと合計の再計算） ---
            const current = readCurrentRows();
            const filtered = current.filter(r => String(r.id) !== String(id));
            renderRows(filtered);
            if (initialIdSet) initialIdSet.delete(String(id));
            updateTotal(filtered);
            showToast('削除しました。', 'success');

          } catch (err) {
            console.error(err);
            showToast('削除エラー: ' + (err?.message || err), 'danger');
          }
        });
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

    // ====== 3) 保存ボタン追従（画面下固定の白透明帯＋ボタン複製） ======
    (function setupFloatingSave() {
      const saveBtn = document.getElementById('saveBtn');
      if (!saveBtn) return;

      // 背景帯
      const bgBar = document.createElement('div');
      bgBar.id = 'saveBtnBackground';
      bgBar.style.position = 'fixed';
      bgBar.style.left = '0';
      bgBar.style.right = '0';
      bgBar.style.bottom = '0';
      bgBar.style.height = '60px';
      bgBar.style.background = 'rgba(255,255,255,0.85)'; // ★ 白透明
      bgBar.style.backdropFilter = 'blur(1px)';
      bgBar.style.zIndex = '998';
      bgBar.style.display = 'none';
      document.body.appendChild(bgBar);

      // 浮動ボタン（同じ挙動でクリック連動）
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
      floatingBtn.disabled = !!isLocked;
      document.body.appendChild(floatingBtn);

      floatingBtn.addEventListener('click', () => {
        if (!isLocked) saveBtn.click();
      });

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

    // ====== 4) 提出フォーム：送信直後に画面を一時ロック ======
    (function setupSubmitSoftLock() {
      const submitFormEl = document.getElementById('submitForm');
      if (!submitFormEl) return;

      submitFormEl.addEventListener('submit', () => {
        // 全ボタン/入力を無効化
        document.querySelectorAll('button, input, select, textarea').forEach(el => {
          el.disabled = true;
        });
        // 送信用ボタン表示切替
        const btn = submitFormEl.querySelector('button[type="submit"]');
        if (btn) { btn.innerText = 'Submitting...'; btn.disabled = true; }

        // 薄いオーバーレイ
        const overlay = document.createElement('div');
        overlay.style.cssText = `
          position:fixed; inset:0; background:rgba(255,255,255,.4);
          backdrop-filter:saturate(120%) blur(1px); z-index:1060;
        `;
        document.body.appendChild(overlay);
      });
    })();

    // 視覚的ロック（任意：CSSがあれば効く）
    if (isLocked) document.body.classList.add('locked');
  });

  // ====== 5) Bootstrap Toast 通知ユーティリティ ======
  (function () {
    // variant: 'primary'|'secondary'|'success'|'danger'|'warning'|'info'|'light'|'dark'
    function showToast(message, variant = 'primary', delay = 3000) {
      const container = document.getElementById('toastContainer') || (() => {
        const div = document.createElement('div');
        div.id = 'toastContainer';
        div.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        div.style.zIndex = '1056';
        document.body.appendChild(div);
        return div;
      })();

      const toastEl = document.createElement('div');
      toastEl.className = `toast align-items-center text-bg-${variant} border-0`;
      toastEl.setAttribute('role', 'status');
      toastEl.setAttribute('aria-live', 'polite');
      toastEl.setAttribute('aria-atomic', 'true');
      toastEl.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">${String(message).replace(/\n/g, '<br>')}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      `;
      container.appendChild(toastEl);

      const toast = new bootstrap.Toast(toastEl, { delay });
      toast.show();
      toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    window.showToast = showToast; // グローバル公開
  })();

  // ====== 6) 提出モーダル起動（data-bs-*で十分だが保険として） ======
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('submitConfirmBtn'); // ない場合もある
    const modalEl = document.getElementById('confirmSubmitModal');
    if (btn && modalEl) {
      const modal = new bootstrap.Modal(modalEl);
      btn.addEventListener('click', () => modal.show());
    }
  });

})();
