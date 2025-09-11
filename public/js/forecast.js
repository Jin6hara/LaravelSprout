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

        // ★ 並び順は sort_order → start → title（ベース仕様）
        eventOrder: 'extendedProps.sort_order,start,title',
        eventOrderStrict: true, // ★ 追加

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
            extraParams: { user_id: window.calendarUserId },
            failure: () => alert('イベント取得に失敗しました'),
            error: (xhr) => console.error('FC error:', xhr?.xhr?.responseText || xhr)
        },

        dayCellContent(info) {
            if (info.view.type === 'dayGridMonth') return info.date.getDate();
            return { html: '' };
        },

        // ★ timeGrid(週/日)では details を絶対配置で描画。その他のビューはタイトルのみ
        eventContent(arg) {
            const ep = arg.event.extendedProps || {};
            const details = Array.isArray(ep.details) ? ep.details : [];

            if (arg.view.type.startsWith('timeGrid') && details.length) {
                const blocks = details.map(d => {
                    const top = typeof d.top === 'number' ? d.top : 0;
                    const h = typeof d.height === 'number' ? d.height : 0;
                    const code = d.lesson_code ?? '';
                    const name = d.lesson_name ?? '';
                    const time = d.start_hm ? ` (${d.start_hm})` : '';
                    const label = (code || name) ? `${code}${code && name ? ' / ' : ''}${name}${time}` : (d.start_hm || '');
                    return `
            <div class="fc-detail-block" style="position:absolute; left:4px; right:4px; top:${top}%; height:${h}%;">
              <div class="fc-detail-title">${label}</div>
            </div>
          `;
                }).join('');

                return { html: `<div class="fc-shift-abs">${blocks}</div>` };
            }

            // 月/リストなどはタイトルだけ
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
                    const left = `
            <span>
              ${d.start_hm ? `${d.start_hm} → ` : ''}${d.lesson_name ?? '—'}${d.lesson_code ? ` (${d.lesson_code})` : ''}
            </span>`;
                    const right = `
            <span class="badge text-bg-primary">
              ${d.lesson_min != null ? `${d.lesson_min}分` : (d.lesson_type ?? '—')}
            </span>`;
                    html += `<li class="list-group-item d-flex justify-content-between">${left}${right}</li>`;
                });
                html += '</ul>';
            } else {
                // ★ 場所は location ではなく school を使用（Providerで school_name を返しているため）
                html += `<div>カテゴリ：${p.category ?? '—'}</div>`;
                html += `<div>種別(type)：${p.type ?? '—'}</div>`;
                html += `<div>場所：${p.school ?? '—'}</div>`;
                html += `<div>状態(status)：${p.status ?? '—'}</div>`;

                const fmt = d => d ? d.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', hour12: false }) : '';
                if (e.start || e.end) {
                    html += `<div>時間：${fmt(e.start)} 〜 ${fmt(e.end)}</div>`;
                }
            }

            document.getElementById('eventModalBody').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            modal.show();
        }
    });

    calendar.render();
});
