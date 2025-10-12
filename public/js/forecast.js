document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    console.log('[] loaded - forecast.js:3');
    function openSubModal(ev) {
        console.log('[] openSubModal - forecast.js:5', ev.extendedProps); // ← 追加
        // ...（モーダル生成はそのまま）
    }

    // ---- Sub 明細モーダル ----
    function openSubModal(ev) {
        const p = ev.extendedProps || {};
        const bd = p.count_breakdown || {};
        const users = p.users || {};
        const absent = p.absent_users || {};

        const pill = (name) => `<span class="badge text-bg-secondary me-1 mb-1">${name}</span>`;
        const group = (title, list) => {
            const body = (list || []).length ? list.map(u => pill(u.name)).join('') : '<span class="text-muted">—</span>';
            return `<div class="mb-2">
      <div class="fw-semibold small text-uppercase text-muted">${title}</div>
      <div>${body}</div>
    </div>`;
        };

        let html = `<div class="mb-2"><strong>Total Subs</strong></div>`; //もともとは${ev.title}
        html += `<div class="mb-2 small text-muted">
    SC:${bd.event ?? 0} / Regular:${bd.line ?? 0} / RWD:${bd.work_instead ?? 0}
  </div>`;
        html += `<h6 class="mt-3">Available Subs</h6>`;
        html += group('SC', users.event);
        html += group('Regular', users.line);
        html += group('RWD', users.work_instead);
        html += `<h6 class="mt-3">Absence Subs</h6>`;
        html += group('SC', absent.event);
        html += group('Regular', absent.line);
        html += group('RWD', absent.work_instead);

        document.getElementById('eventModalBody').innerHTML = html;

        const modalEl = document.getElementById('eventModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl); // ← 再利用
        modal.show();
    }

    // これで十分（背景イベントでも eventClick が飛びます）
    function isSubEvt(ev) {
        return ev?.extendedProps?.category === 'subcount' || !!ev?.extendedProps?.users;
    }
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
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
        buttonText: { today: '今日', month: '月', week: '週', day: '日', list: 'リスト' },
        dayMaxEventRows: true,
        navLinks: true,
        nowIndicator: true,

        events: {
            url: window.calendarEventsUrl,
            failure: () => alert('イベント取得に失敗しました'),
            error: (xhr) => console.error('FC error: - forecast.js:68', xhr?.xhr?.responseText || xhr)
        },

        dayCellContent(info) {
            if (info.view.type === 'dayGridMonth') return info.date.getDate();
            return { html: '' };
        },

        eventContent(arg) {
            return { html: arg.event.title };
        },

        // 背景イベントは eventClick が発火しないことがあるため DOM に直接 click を bind
        eventDidMount(info) {
            if (isSubEvt(info.event)) {
                info.el.addEventListener('click', (e) => {
                    e.preventDefault();
                    console.log('[] bg click - forecast.js:85', info.event.title); // ← 追加
                    openSubModal(info.event);
                });
            }
        },

        // ★ eventDidMount での click 付与は削除 or 無効化（二重起動防止）
        // eventDidMount(info) { /* なし */ }
        eventClick(info) {
            const ev = info.event;
            if (isSubEvt(ev)) {
                info.jsEvent?.preventDefault();
                info.jsEvent?.stopPropagation();
                openSubModal(ev);
                return;
            }

            // 既存の汎用表示（祝日・会社休暇）
            const e = info.event, p = e.extendedProps || {};
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
