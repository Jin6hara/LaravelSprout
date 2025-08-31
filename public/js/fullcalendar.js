document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'ja',
        initialView: 'dayGridMonth',
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
        buttonText: { today: '今日', month: '月', week: '週', day: '日', list: 'リスト' },
        dayMaxEventRows: true,
        navLinks: true,
        nowIndicator: true,
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
            if (p.details) {
                html += '<ul class="list-group">';
                p.details.forEach(d => {
                    html += `<li class="list-group-item d-flex justify-content-between">
                     <span>${d.campus} (${d.reason ?? '—'})</span>
                     <span class="badge text-bg-danger">必要 ${d.needed}</span>
                   </li>`;
                });
                html += '</ul>';
            } else {
                html += `<div>種別：${p.type ?? '—'}</div>`;
                html += `<div>場所：${p.location ?? '—'}</div>`;
                if (p.start_time || p.end_time) {
                    html += `<div>時間：${p.start_time ?? ''} 〜 ${p.end_time ?? ''}</div>`;
                }
            }
            document.getElementById('eventModalBody').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            modal.show();
        },
    });
    calendar.render();
});