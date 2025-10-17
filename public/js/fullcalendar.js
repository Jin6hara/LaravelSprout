document.addEventListener('DOMContentLoaded', function () {
    // ▼▼ これによりLessonの終了時間を計算 ▼▼
    // HH:MM 形式の文字列に分数を加算して HH:MM 形式で返す
    function addMinutesToHHMM(hhmm, minutes) {
        if (!hhmm || minutes == null) return '';
        const [h, m] = hhmm.split(':').map(n => parseInt(n, 10));
        if (Number.isNaN(h) || Number.isNaN(m)) return '';
        const total = h * 60 + m + Number(minutes);
        const MIN_DAY = 24 * 60;
        const norm = ((total % MIN_DAY) + MIN_DAY) % MIN_DAY; // 0..1439 に正規化
        const hh = Math.floor(norm / 60);
        const mm = norm % 60;
        return String(hh).padStart(2, '0') + ':' + String(mm).padStart(2, '0');
    }
    // ▲▲ これによりLessonの終了時間を計算 ▲▲

    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'en',
        initialView: 'dayGridMonth',
        initialDate: window.initialDate,
        height: 'auto',
        firstDay: 0,
        // 10分単位でスロットを区切る
        slotDuration: '00:10:00',
        // ラベルを 09:00, 09:10, … のように24h表記
        slotLabelFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },
        // 表示範囲を制限
        slotMinTime: '09:00:00',
        slotMaxTime: '23:00:00',
        eventOrder: "extendedProps.category,title,start",
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        //buttonText: { today: '今日', month: '月', week: '週', day: '日', list: 'リスト' },
        dayMaxEventRows: true,
        navLinks: true,
        nowIndicator: true,
        editable: false,
        selectable: true,
        events: {
            url: window.calendarEventsUrl, // テンプレート埋め込み用
            extraParams: { user_id: window.calendarUserId }, // テンプレート埋め込み用
            failure: () => alert('イベント取得に失敗しました'),
            error: (xhr) => console.error('FC error:', xhr?.xhr?.responseText || xhr)
        },
        // 週の日付表示制御
        dayCellContent(info) {
            if (info.view.type === 'dayGridMonth') {
                return info.date.getDate();      // 月表示だけ日付数字
            }
            return { html: '' };               // timeGrid(週/日)では描画しない
        },
        eventContent: function (arg) {
            return { html: arg.event.title };
        },

        eventClick(info) {

            info.jsEvent.preventDefault();
            const e = info.event;
            const p = e.extendedProps || {};
            let html = `<div class="mb-2"><strong>${e.title}</strong></div>`;

            if (Array.isArray(p.details) && p.details.length) {
                html += '<ul class="list-group">';
                p.details.forEach(d => {
                    const start = d.start_hm || '';
                    const min = (d.lesson_min != null ? Number(d.lesson_min) : null);
                    const end = (start && min != null) ? addMinutesToHHMM(start, min) : '';
                    const range = (start && end) ? `${start}~${end}` : (start || '—');

                    const name = d.lesson_name ?? '—';
                    const code = d.lesson_code ? ` (${d.lesson_code})` : '';

                    // 青バッジ部（lesson_min が無ければ type を代替表示）
                    const badge = `<span class="badge text-bg-primary">
                ${min != null ? `${min}分` : (d.lesson_type ?? '—')}
                </span>`;

                html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>${range} ${name}${code}</span>
                ${badge}
                </li>`;
                });
                html += '</ul>';
                // ▲▲ 詳細があればリストで表示 ▲▲
            } else {
                // ▼▼ 詳細がないときは従来の簡易情報 ▼▼
                //html += `<div>Type: ${p.kind ?? ''}</div>`;
                if (p.closure_code) html += `<div>Category: ${p.closure_code}</div>`;
                const fmt = d => d ? d.toLocaleDateString('ja-JP') : '';
                //if (e.start || e.end) html += `<div>日付：${fmt(e.start)}${e.end ? ' 〜 ' + fmt(e.end) : ''}</div>`; //endは使わないので削除
                if (e.start || e.end) html += `<div>Date: ${fmt(e.start)}</div>`;
            }   // ▲▲ 詳細がないときは従来の簡易情報 ▲▲

            document.getElementById('eventModalBody').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            modal.show();
        }
    });
    calendar.render();
});