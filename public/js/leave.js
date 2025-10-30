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
            // 上記の代わり：編集リンク設定（leave.id がある前提）
            if (editBtn) {
                const lid = e?.id ?? p?.id ?? null; // 念のため extendedProps.id もフォールバック
                if (lid) {
                    // Absence Manager（leaves.edit）へ leave_id で絞り込み
                    editBtn.href = `/leave_manager?leave_id=${encodeURIComponent(lid)}`;
                    editBtn.style.removeProperty('display');  // display:none を解除
                    editBtn.classList.remove('disabled');
                    editBtn.setAttribute('aria-disabled', 'false');
                }
            }
            // end here

           // 既存のモーダルを再利用
           const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('eventModal'));
           modal.show();
        }
    });

    calendar.render();
});
