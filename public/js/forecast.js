// public/js/forecast.js
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'ja',
        initialView: 'dayGridMonth',
        height: 'auto',
        firstDay: 0,
        slotDuration: '00:10:00',
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
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
            url: window.calendarEventsUrl,
            failure: () => alert('イベント取得に失敗しました'),
            error: (xhr) => console.error('FC error:', xhr?.xhr?.responseText || xhr)
        },

        dayCellContent(info) {
            if (info.view.type === 'dayGridMonth') return info.date.getDate();
            return { html: '' };
        },

        eventContent(arg) {
            // 背景イベント中心なのでタイトルをシンプルに
            return { html: arg.event.title };
        },

        eventClick(info) {
            info.jsEvent.preventDefault();
            const e = info.event;
            const p = e.extendedProps || {};
            let html = `<div class="mb-2"><strong>${e.title}</strong></div>`;
            html += `<div>種別：${p.kind ?? '—'}</div>`;
            if (p.closure_code) html += `<div>区分：${p.closure_code}</div>`;
            const fmt = d => d ? d.toLocaleDateString('ja-JP') : '';
            if (e.start || e.end) html += `<div>日付：${fmt(e.start)}${e.end ? ' 〜 ' + fmt(e.end) : ''}</div>`;
            document.getElementById('eventModalBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('eventModal')).show();
        }
    });

    calendar.render();
});
