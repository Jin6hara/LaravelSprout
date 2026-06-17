document.addEventListener('DOMContentLoaded', function () {
    const storedView = localStorage.getItem('leaveCalendarView');
    const savedView = ['dayGridMonth', 'listMonth'].includes(storedView)
        ? storedView
        : 'dayGridMonth';
    const urlMonth = new URLSearchParams(location.search).get('month');
    const savedDate = urlMonth ? window.initialDate : (localStorage.getItem('leaveCalendarDate') || window.initialDate);
    const calendarEl = document.getElementById('calendar');
    console.log('[] loaded - leave.js:3');

    // ▼▼ FullCalendar 初期化（基本のみ）▼▼
    const calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'en',
        initialView: savedView,
        initialDate: savedDate,
        height: 'auto',
        firstDay: 0,
        slotDuration: '00:10:00',
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        slotMinTime: '09:00:00',
        slotMaxTime: '23:00:00',
        eventOrder: "extendedProps.category,title,start",
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
        dayMaxEventRows: true,
        navLinks: true,
        displayEventTime: false, //Listの時間を表示しない
        nowIndicator: true,
        validRange: { start: '2025-04-01', end: '' },
        editable: false,

        views: {
            listMonth: {
                listDayFormat: {
                    weekday: 'long',
                    month: 'short',
                    day: 'numeric'
                },
                listDaySideFormat: false
            }
        },

        datesSet(info) {
            const d = info.view.currentStart;
            const localDate = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            localStorage.setItem('leaveCalendarView', info.view.type);
            localStorage.setItem('leaveCalendarDate', localDate);
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

            // ★毎回タイトル初期化＆編集ボタンを初期化（非表示/無効化）
            const titleEl = document.querySelector('#eventModal .modal-title');
            if (titleEl) titleEl.textContent = 'Details';
            const editBtn = document.getElementById('eventEditLink');
            if (editBtn) {
                editBtn.href = '#';
                editBtn.style.display = 'none';
                editBtn.classList.add('disabled');
                editBtn.setAttribute('aria-disabled', 'true');
            }
            // ★ end here

            // ★関連 Event ボタン初期化
            const relatedBtn = document.getElementById('relatedEventLink');
            if (relatedBtn) {
                relatedBtn.href = '#';
                relatedBtn.style.display = 'none';
                relatedBtn.classList.add('disabled');
                relatedBtn.setAttribute('aria-disabled', 'true');
            }
            // ★ end here

            // ★Daily Board ボタン初期化
            const dayBoardBtn = document.getElementById('dayBoardLink');
            if (dayBoardBtn) {
                dayBoardBtn.href = '#';
                dayBoardBtn.style.display = 'none';
                dayBoardBtn.classList.add('disabled');
                dayBoardBtn.setAttribute('aria-disabled', 'true');
            }
            // ★ end here

            const title = e.title || 'Leave';
            const fmt = (d) => d ? d.toLocaleDateString('ja-JP') : '';

            let html = `<div class="mb-2"><strong>${title}</strong></div>`;
            if (e.start || e.end) {
                html += `<div class="mt-2 mb-2">Date: ${fmt(e.start)}</div>`; // ${e.end ? ' 〜 ' + fmt(e.end) : ''}
            }
            //if (p.leave?.kind)   html += `<div class="small text-muted">Kind: ${p.leave.kind}</div>`; タイトルにあるため削除
            if (p.leave?.status) html += `<div class="small text-muted">Status: ${p.leave.status}</div>`;
            //if (p.user?.name) {
                //const emp = p.user?.employee_code ? ` [${p.user.employee_code}]` : '';
                //html += `<div>User: ${p.user.name}${emp}</div>`;
            //}　タイトルにあるため削除、学習用として残しておく。
            // ★Handle_type
            if (p.leave?.handle_type) {
                html += `<div class="small text-muted">Handle Type: ${p.leave.handle_type}</div>`;
            }
            // ★excused
            if (p.leave?.excused) {
                html += `<div class="small text-muted">Excused: ${p.leave.excused}</div>`;
            }
            // ★Reason（改行対応）
            if (p.leave?.reason) {
                 const reasonHtml = String(p.leave.reason).replace(/\n/g, '<br>');
                html += `<div class="mt-2 mb-2 small text-muted">Reason: ${reasonHtml}</div>`;
            }

            document.getElementById('eventModalBody').innerHTML = html;
            // new bootstrap.Modal(document.getElementById('eventModal')).show();　←削除
            // 上記の代わり：編集リンク設定（期間 + original_user_id で絞り込み）
            if (editBtn) {
                const uid   = p.user?.id ?? null; // original_user_id = Leaveのuser_id
                const sDate = p.leave?.start_date ?? (e.start ? e.start.toISOString().slice(0,10) : null);
                const eDate = p.leave?.end_date   ?? sDate; // end_date が無ければ単日
                if (uid && sDate) {
                    const url = `/leave_manager?user_id=${encodeURIComponent(uid)}&start_date=${encodeURIComponent(sDate)}${eDate ? `&end_date=${encodeURIComponent(eDate)}` : ''}`;
                    editBtn.href = url;
                    editBtn.style.removeProperty('display'); // display:none を解除
                    editBtn.classList.remove('disabled');
                    editBtn.setAttribute('aria-disabled', 'false');
                }
            }
            // end here

            // ★対応する Event へのリンク設定（期間 + original_user_id で検索）
            const uid2   = p.user?.id ?? null;                                   // original_user_id
            const sDate2 = p.leave?.start_date ?? (e.start ? e.start.toISOString().slice(0,10) : null);
            const eDate2 = p.leave?.end_date   ?? sDate2;
            if (relatedBtn && uid2 && sDate2) {
                const url2 = `/shift_assigner?original_user_id=${encodeURIComponent(uid2)}&event_date=${encodeURIComponent(sDate2)}${eDate2 ? `&end_date=${encodeURIComponent(eDate2)}` : ''}`;
                relatedBtn.href = url2;
                relatedBtn.style.removeProperty('display');
                relatedBtn.classList.remove('disabled');
                relatedBtn.setAttribute('aria-disabled', 'false');
            }
            // ★ end here

            // ★Daily Board へのリンク設定（関連シフト日付がある場合のみ）
            if (dayBoardBtn && sDate2) {
                dayBoardBtn.href = `/forecast/day-assigner?date=${encodeURIComponent(sDate2)}`;
                dayBoardBtn.style.removeProperty('display');
                dayBoardBtn.classList.remove('disabled');
                dayBoardBtn.setAttribute('aria-disabled', 'false');
            }
            // ★ end here

            // 既存のモーダルを再利用
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('eventModal'));
            modal.show();
        }
    });

    calendar.render();
});
