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

            if (Array.isArray(p.details) && p.details.length) {
                html += '<ul class="list-group">'; // ← 追加
                p.details.forEach(d => {
                    const left = `
        <span>
          ${d.start_hm ? `${d.start_hm} → ` : ''}
          ${d.lesson_name ?? '—'}
          ${d.lesson_code ? ` (${d.lesson_code})` : ''}
        </span>
      `;
                    const right = `
        <span class="badge text-bg-primary">
          ${d.lesson_min != null ? `${d.lesson_min}分` : (d.lesson_type ?? '—')}
        </span>
      `;
                    html += `<li class="list-group-item d-flex justify-content-between">${left}${right}</li>`;
                });
                html += '</ul>'; // ← 閉じタグはそのまま
            } else {
                // CandidateEvent で extendedProps に入れている category/location を使う
                html += `<div>種別：${p.category ?? '—'}</div>`;
                html += `<div>場所：${p.location ?? '—'}</div>`;
                // start/end はトップレベルの Date を整形して表示
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