document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    console.log('[] loaded - forecast.js:3');
    function openSubModal(ev) {
        console.log('[] openSubModal - forecast.js:5', ev.extendedProps);
    }

    // ▼▼ Total Subs 明細モーダル ▼▼
    function openSubModal(ev) {
        const p = ev.extendedProps || {};
        const bd = p.count_breakdown || {};
        const users = p.users || {};
        const absent = p.absent_users || {};

        // 件数を決定（countがなければ内訳合計）
        const count = (typeof p.count === 'number')
            ? p.count
            : ((bd.line || 0) + (bd.work_instead || 0) + (bd.subs || 0)); // ← subs 追加
        const displayTitle = `Total Subs ${count}`; 

        // Detailsの代わりに下記を表示することもできる（ヘッダのタイトルも更新）
        //const titleEl = document.querySelector('#eventModal .modal-title'); 
        //if (titleEl) titleEl.textContent = displayTitle; 

        // name だけ or Extra(=subs)では name + 時間(HH:MM–HH:MM) を小さく表示
        const pill = (item, showTime = false) => {
            // item は {name, start_hm?, end_hm?} もしくは 'string'
            const name = (item && typeof item === 'object') ? (item.name ?? '') : String(item ?? '');
            const timeText = (showTime && item && typeof item === 'object')
                ? ((item.start_hm || item.end_hm)
                    ? `${item.start_hm ?? ''}${(item.start_hm && item.end_hm) ? '–' : ''}${item.end_hm ?? ''}`
                    : '')
                : '';
            const timeHtml = timeText
                ? `<div class="small text-muted" style="line-height:1;margin-top:0px;">${timeText}</div>`
                : '';

            // 縦積み（バッジ下に小さく時間）
            return `<div class="d-inline-flex flex-column align-items-start me-1 mb-1">
                        <span class="badge text-bg-warning">${name}</span>
                        ${timeHtml}
                    </div>`;
        };

        const group = (title, list, showTime = false) => {
            const body = (list || []).length
                ? list.map(u => pill(u, showTime)).join('')
                : '<span class="text-muted">—</span>';
            return `<div class="mb-2">
        <div class="fw-semibold small text-uppercase text-muted">${title}</div>
            <div>${body}</div>
        </div>`;
        };
        // ここはTotal Subsのタイトル表示に関連。
        let html = `<h5 class="mb-2"><strong>${displayTitle}</strong></h5>`; // ← 修正（もともとは${ev.title}）
        html += `<div class="mb-2 small text-muted">
            Regular:${bd.line ?? 0} / RWD:${bd.work_instead ?? 0} / Extra:${bd.subs ?? 0}
        </div>`;
        html += `<h5 class="mt-5 mb-2">Available Subs</h5>`;
        html += group('Regular Subs', users.line, /*showTime=*/false);
        html += group('Rostered Working Day', users.work_instead, /*showTime=*/false);
        html += group('Extra Subs', users.subs, /*showTime=*/true); // ← Extra(=subs)のみ時間表示

        html += `<h5 class="mt-5 mb-2">Absence Subs</h5>`;
        html += group('Regular Subs', absent.line, /*showTime=*/false);
        html += group('Rostered Working Day', absent.work_instead, /*showTime=*/false);
        html += group('Extra Subs', absent.subs, /*showTime=*/true); // ← Absence 側も subs は時間表示

        document.getElementById('eventModalBody').innerHTML = html;

        const modalEl = document.getElementById('eventModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl); // ← 再利用
        modal.show();
    }
    // ▲▲ Total Subs 明細モーダル ▲▲

    // ▼▼ これがないとモーダル開けない ▼▼
    function isSubEvt(ev) {
        return ev?.extendedProps?.category === 'subcount' || !!ev?.extendedProps?.users;
    }
    // ▲▲ これがないとモーダル開けない ▲▲

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

    // ▼▼ FullCalendar 初期化 ▼▼
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
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' }, // timeGridWeek（週）は除外
        //buttonText: { today: 'Today', month: 'Month', week: 'Week', day: 'day', list: 'List' },    // locale: 'en'の為英語設定不要。
        dayMaxEventRows: true,
        navLinks: true,
        nowIndicator: true,
        validRange: { start: '2025-04-01', end: '' },
        editable: true, //eventDrop: (info) => updateEvent(info.event), // ← updateもできるらしい
        //その他面白い機能：selectable, selectMirror, dayMaxEvents, weekends, businessHours, etc.

        views: {
            listWeek: {
                listDayFormat: { weekday: 'long' }, // Monday のように
                listDaySideFormat: { month: 'short', day: 'numeric' } // Jan 1 のように
            }
        },

        events: {
            url: window.calendarEventsUrl,
            failure: () => alert('イベント取得に失敗しました'),
            error: (xhr) => console.error('FC error: - forecast.js:78', xhr?.xhr?.responseText || xhr)
        },

        dayCellContent(info) {
            if (info.view.type === 'dayGridMonth') return info.date.getDate();
            return { html: '' };
        },

        // ▼▼ Event枠内の表示 ▼▼
        eventContent(arg) {
            const p = arg.event.extendedProps || {};

            // ① タイトル
            const base = arg.event.title;

            // ② 講師表示（original → assigned）
            const nameOrig = p.original_user_name || null;
            const nameAssign = p.assigned_user_name || null;
            // ない場合はIDでも可（どちらも無ければ省略）
            const idAssign = p.assigned_user_id != null ? `#${p.assigned_user_id}` : null;

            let who = '';
            if (nameOrig && (nameAssign || idAssign)) {
                who = ` <span class="text-white small ms-1">(${nameOrig} → ${nameAssign ?? idAssign})</span>`;
            } else if (nameOrig) {
                who = ` <span class="text-white small ms-1">(${nameOrig})</span>`;
            } else if (nameAssign || idAssign) {
                who = ` <span class="text-white small ms-1">(→ ${nameAssign ?? idAssign})</span>`;
            }

            // ③ lesson_code（details からユニーク抽出して横に表示）
            let codesHtml = '';
            if (Array.isArray(p.details) && p.details.length) {
                const codes = [...new Set(
                    p.details.map(d => d?.lesson_code).filter(Boolean)
                )];
                if (codes.length) {
                    // 小さなバッジ風に（控えめ表示）
                    const pills = codes.map(c => `<span class="badge text-bg-light border ms-1">${c}</span>`).join('');
                    codesHtml = ` <span class="ms-1">${pills}</span>`;
                }
            }

            const html = `${base}${who}${codesHtml}`;
            return { html };
        },
        // ▲▲ Event枠内の表示 ▲▲

        // ▼▼ Sub背景, Event枠クリック時の動作 ▼▼
        eventClick(info) {
            const ev = info.event;
            //モーダルタイトルを毎回初期化（背景で "Total Subs ..." を表示したあとに残らないように）
            const titleEl = document.querySelector('#eventModal .modal-title');
            if (titleEl) titleEl.textContent = 'Details';

            // ★編集ボタンを毎回初期化（非表示/無効化）
            const editBtn = document.getElementById('eventEditLink');
            if (editBtn) {
                editBtn.href = '#';
                editBtn.style.display = 'none';
                editBtn.classList.add('disabled');
                editBtn.setAttribute('aria-disabled', 'true');
            }
            // end ★編集ボタンの初期化

            // ★Leaveボタンの初期化
            const leaveBtn = document.getElementById('leaveEditLink');
            if (leaveBtn) {
                leaveBtn.href = '#';
                leaveBtn.style.display = 'none';
                leaveBtn.classList.add('disabled');
                leaveBtn.setAttribute('aria-disabled', 'true');
            }
            // end ★Leaveボタンの初期化

            if (isSubEvt(ev)) {
                info.jsEvent?.preventDefault();
                info.jsEvent?.stopPropagation();
                openSubModal(ev);
                return;
            }

            // モーダルの表示内容構築
            const e = info.event, p = e.extendedProps || {}; //休日背景のモーダル表示
            const name = p.original_user_name;               //original_user_name を取得
            const header = name
                ? `${e.title} <span class="text-muted small ms-1">(${name})</span>`
                : e.title;
            // ▼▼　assigned_user_name または assigned_user_id ▼▼  
            const assignDisp = p.assigned_user_name ?? (p.assigned_user_id != null ? `#${p.assigned_user_id}` : null);
            let html = `<div class="mb-2"><strong>${header}</strong></div>`;
            if (assignDisp) {
                html += `<div class="small text-dark mb-2">Assigned: ${assignDisp}</div>`;
            }
            // ▲▲ assigned_user_name または assigned_user_id ▲▲

            // ▼▼ 詳細があればリストで表示 ▼▼
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
                // イベントのノート（改行は <br> に）
                if (p.notes) {
                    const noteHtml = String(p.notes).replace(/\n/g, '<br>');
                    html += `<div class="mt-2 mb-2 small text-muted">Note: ${noteHtml}</div>`;
                }
                // ▲▲ 詳細があればリストで表示 ▲▲
            } else {
                // ▼▼ 詳細がないときは従来の簡易情報 ▼▼
                if (p.notes) {
                    const noteHtml = String(p.notes).replace(/\n/g, '<br>');
                    html += `<div class="mt-2 mb-2 small text-muted">Note: ${noteHtml}</div>`;
                }
                //html += `<div>Type: ${p.kind ?? ''}</div>`;　// kind は使わないので削除
                if (p.closure_code) html += `<div>Category: ${p.closure_code}</div>`;
                const fmt = d => d ? d.toLocaleDateString('ja-JP') : '';
                //if (e.start || e.end) html += `<div>日付：${fmt(e.start)}${e.end ? ' 〜 ' + fmt(e.end) : ''}</div>`; //endは使わないので削除
                if (e.start || e.end) html += `<div>Date: ${fmt(e.start)}</div>`;
            }   // ▲▲ 詳細がないときは従来の簡易情報 ▲▲

            // ★ イベント枠の場合のみ編集リンクを設定して表示
            if (editBtn) {
                const eid = e?.id ?? p?.id ?? null; // event.id を最優先、なければ extendedProps.id
                if (eid) {
                    editBtn.href = `/shift_assigner?event_id=${encodeURIComponent(eid)}`; // name('calendar.edit')
                    editBtn.style.removeProperty('display'); // display:none を解除
                    editBtn.classList.remove('disabled');
                    editBtn.setAttribute('aria-disabled', 'false');
                }
            }
            // end ★ イベント枠の場合のみ編集リンクを設定して表示

            // ★対応する Leave へのリンク設定（source_leave_id がある場合のみ）
            if (leaveBtn) {
                const lid = p?.source_leave_id ?? null;
                if (lid) {
                    leaveBtn.href = `/leave_manager?leave_id=${encodeURIComponent(lid)}`;
                    leaveBtn.style.removeProperty('display'); // display:none を解除
                    leaveBtn.classList.remove('disabled');
                    leaveBtn.setAttribute('aria-disabled', 'false');
                }
            }
            // end ★対応する Leave へのリンク設定

            document.getElementById('eventModalBody').innerHTML = html;
            // 既存のモーダルを再利用（どちらでもOKだが再利用の方が自然）
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('eventModal'));
            modal.show();
        }
        // ▲▲ Sub背景, Event枠クリック時の動作 ▲▲
    });

    calendar.render();
});
