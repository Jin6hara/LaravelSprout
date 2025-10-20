document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    console.log('[] loaded - leave.js:3');

    // ▼▼ FullCalendar 初期化（基本のみ）▼▼
    const calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'en',
        initialView: 'dayGridMonth',
        initialDate: window.initialDate,
        height: 'auto',
        firstDay: 0,
        slotDuration: '00:10:00',
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        slotMinTime: '09:00:00',
        slotMaxTime: '23:00:00',
        eventOrder: "extendedProps.category,title,start",
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' },
        dayMaxEventRows: true,
        navLinks: true,
        nowIndicator: true,
        validRange: { start: '2025-04-01', end: '' },
        editable: false,

        views: {
            listWeek: {
                listDayFormat: { weekday: 'long' },
                listDaySideFormat: { month: 'short', day: 'numeric' }
            }
        },

        events: {
            url: window.calendarEventsUrl,
            failure: () => alert('イベント取得に失敗しました'),
            error: (xhr) => console.error('FC error - leave.js:28', xhr?.xhr?.responseText || xhr)
        },

        dayCellContent(info) {
            if (info.view.type === 'dayGridMonth') return info.date.getDate();
            return { html: '' };
        },

        // ▼▼ Event枠内の表示（タイトルのみ）▼▼
        eventContent(arg) {
            return { html: `${arg.event.title ?? ''}` };
        },

        // ▼▼ クリックで簡易モーダル（基本情報のみ）▼▼
        eventClick(info) {
            const e = info.event;
            const p = e.extendedProps || {};

            const title = e.title || 'Leave';
            const fmt = (d) => d ? d.toLocaleDateString('ja-JP') : '';

            let html = `<div class="mb-2"><strong>${title}</strong></div>`;
            if (e.start || e.end) {
                html += `<div>Date: ${fmt(e.start)}${e.end ? ' 〜 ' + fmt(e.end) : ''}</div>`;
            }
            if (p.kind)   html += `<div>Kind: ${p.kind}</div>`;
            if (p.status) html += `<div>Status: ${p.status}</div>`;
            if (p.user_name) html += `<div>User: ${p.user_name}</div>`;

            document.getElementById('eventModalBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('eventModal')).show();
        }
    });

    calendar.render();
});
