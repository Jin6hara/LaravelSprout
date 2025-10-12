document.addEventListener('DOMContentLoaded', function () {
  if (!@json($hasReport)) return; // レポート無い月は何もしない

  const csrfToken   = @json(csrf_token());
  const reportId    = @json($report?->id);
  const year        = @json($y);
  const month       = @json($m);
  const initialRows = @json($rows);

  // 英語の曜日
  function enWeekday(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d)) return '';
    return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()];
  }

  // TripType Enum
  const tripTypeOptions = [
    { id: 'round_trip', name: 'Round Trip' },
    { id: 'one_way',    name: 'One Way' },
  ];

  // 初期データ行（表示列＋非表示の内部列）
  // 列: Date / Day / From / To / Amount / TripType(ENUM値) / Note / _id / _seq
  const matrix = initialRows.map(r => {
    const date = r.expense_date || '';
    return [
      date,
      enWeekday(date),
      r.station_from || '',
      r.station_to   || '',
      Number.isFinite(r.cost) ? r.cost : 0,
      r.trip_type || '',
      r.note || '',
      r.id ?? '',
      (r.seq ?? 100)
    ];
  });

  // JSpreadsheet
  const sheet = jspreadsheet(document.getElementById('sheet'), {
    worksheets: [
      {
        data: matrix,
        columns: [
          { title:'Date',           type:'text',     width:120, readOnly:true  }, // 0
          { title:'Day',            type:'text',     width:70,  readOnly:true  }, // 1
          { title:'From',           type:'text',     width:160                    }, // 2
          { title:'To',             type:'text',     width:160                    }, // 3
          { title:'Amount (JPY)',   type:'numeric',  width:130, mask:'#,##0'     }, // 4
          { title:'Trip Type',      type:'dropdown', width:140, source: tripTypeOptions }, // 5 (値は id が入る)
          { title:'Note',           type:'text',     width:220                    }, // 6
          { title:'_id',            type:'text',     visible:false                }, // 7
          { title:'_seq',           type:'numeric',  visible:false                }, // 8
        ],
        // 表示だけの空行は作らない（保存の判定がややこしくなるため）
        minDimensions: [9, Math.max(matrix.length, 1)],
      }
    ]
  });

  // 便利関数：現在の全行データをオブジェクト配列に
  function readCurrentRows() {
    const data = sheet[0].getData(false); // 各セルの生値
    // 値をオブジェクトへマッピング
    return data.map(arr => ({
      date:  arr[0] || '',
      day:   arr[1] || '',
      from:  arr[2] || '',
      to:    arr[3] || '',
      cost:  (arr[4] === '' || arr[4] == null) ? 0 : Number(String(arr[4]).replace(/,/g,'')),
      trip:  arr[5] || '', // dropdown は id が入る（round_trip / one_way）
      note:  arr[6] || '',
      id:    arr[7] || '',
      seq:   (arr[8] === '' || arr[8] == null) ? 100 : Number(arr[8]),
    })).filter(r => r.date); // 日付なしは無視
  }

  // 既存IDの集合（更新判定用）
  const initialIdSet = new Set(initialRows.map(r => String(r.id)));

  // 保存処理
  const saveBtn = document.getElementById('saveBtn');
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      const rows = readCurrentRows();

      // バリデーション（この月内のデータか）
      for (const r of rows) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(r.date)) {
          alert(`日付形式エラー: ${r.date}`); return;
        }
        const [yy, mm] = r.date.split('-').map(Number);
        if (yy !== Number(year) || mm !== Number(month)) {
          alert(`この月以外の日付が含まれています: ${r.date}`); return;
        }
        if (r.cost < 0 || !Number.isFinite(r.cost)) {
          alert(`金額が不正です: ${r.cost}`); return;
        }
        // TripType は空でも可（サーバー側で拒否されるのでここで促す）
        if (!r.trip) {
          alert(`Trip Type が未選択の日があります: ${r.date}`); return;
        }
      }

      saveBtn.disabled = true; saveBtn.textContent = '保存中…';

      try {
        // 1) UPDATE: id がある行
        const updates = rows.filter(r => r.id && initialIdSet.has(String(r.id)));
        for (const u of updates) {
          const resp = await fetch(`/api/expenses/${u.id}`, {
            method: 'PUT',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              station_from: u.from || null,
              station_to:   u.to   || null,
              note:         u.note || null,
              cost:         u.cost,
              trip_type:    u.trip,   // 'round_trip' | 'one_way'
              seq:          u.seq,
              // category は送らない（保持）
            }),
          });
          if (!resp.ok) {
            const t = await resp.text();
            throw new Error(`更新失敗 (ID:${u.id}): ${resp.status} ${t}`);
          }
        }

        // 2) CREATE: id が無い行
        const creates = rows.filter(r => !r.id);
        for (const c of creates) {
          // seq が未設定なら 100
          const seq = Number.isFinite(c.seq) ? c.seq : 100;
          const resp = await fetch('/api/expenses', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              expense_report_id: reportId,
              expense_date:      c.date,
              seq:               seq,
              station_from:      c.from || null,
              station_to:        c.to   || null,
              note:              c.note || null,
              cost:              c.cost,
              trip_type:         c.trip,      // 'round_trip' | 'one_way'
              category:          'regular',   // Category列を削除したため既定で regular
              // commuter_pass_id: null,
            }),
          });
          if (!resp.ok) {
            const t = await resp.text();
            throw new Error(`作成失敗 (Date:${c.date}): ${resp.status} ${t}`);
          }
        }

        alert('保存しました。');
        // 画面再読み込みで最新を表示（ID 付与など反映）
        location.reload();

      } catch (err) {
        console.error(err);
        alert('保存でエラーが発生しました。\n' + (err?.message || err));
      } finally {
        saveBtn.disabled = false; saveBtn.textContent = '保存';
      }
    });
  }
});