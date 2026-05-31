document.addEventListener('DOMContentLoaded', function () {
  const config = window.dailyShiftBoard || {};
  const selectedDate = config.selectedDate;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    || document.querySelector('input[name="_token"]')?.value;
  const statusEl = document.getElementById('dailyBoardSaveStatus');
  let statusTimer = null;

  function setStatus(message, kind = 'muted', autoClearMs = null) {
    if (!statusEl) return;
    clearTimeout(statusTimer);
    statusEl.textContent = message || '';
    statusEl.className = message ? `small text-${kind}` : 'small text-muted d-none';
    if (autoClearMs && message) {
      statusTimer = setTimeout(() => {
        statusEl.textContent = '';
        statusEl.className = 'small text-muted d-none';
      }, autoClearMs);
    }
  }

  function savedMessage(rows, data) {
    if (rows.length === 1) {
      return `Event #${rows[0].dataset.eventId} saved.`;
    }
    const count = Number(data.updated || rows.length);
    return `${count} events saved.`;
  }

  function failedMessage(rows, data) {
    if (rows.length === 1) {
      return `Event #${rows[0].dataset.eventId} failed to save.`;
    }
    return data.message || 'Some events failed to save.';
  }

  function hhmmToMin(t) {
    if (!t) return null;
    const parts = String(t).trim().replace('：', ':').split(':');
    if (parts.length < 2) return null;
    const h = Number(parts[0]);
    const m = Number(parts[1]);
    if (Number.isNaN(h) || Number.isNaN(m)) return null;
    return h * 60 + m;
  }

  function pad2(n) {
    return String(n).padStart(2, '0');
  }

  function minToHhmm(min) {
    const normalized = ((min % 1440) + 1440) % 1440;
    return `${pad2(Math.floor(normalized / 60))}:${pad2(normalized % 60)}`;
  }

  function recalcTotal(row, force = false) {
    const start = row.querySelector('[name="start_time"]')?.value || '';
    const end = row.querySelector('[name="end_time"]')?.value || '';
    const out = row.querySelector('[name="total_duration"]');
    if (!out) return;
    if (!force && out.value) return;

    const s = hhmmToMin(start);
    const e = hhmmToMin(end);
    if (s == null || e == null) return;

    let diff = e - s;
    if (diff < 0) diff += 1440;
    out.value = Math.floor(diff / 60) + ':' + pad2(diff % 60);
  }

  function collectRow(row) {
    const data = { id: Number(row.dataset.eventId) };
    row.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
      data[field.name] = field.value;
    });
    return data;
  }

  async function saveRows(rows) {
    const targetRows = Array.from(rows).filter(Boolean);
    if (!targetRows.length) {
      setStatus('No rows to save.', 'warning', 3000);
      return false;
    }

    targetRows.forEach((row) => {
      row.classList.remove('is-saved', 'is-error');
      row.classList.add('is-saving');
      recalcTotal(row, true);
    });

    setStatus('Saving...', 'muted');

    try {
      const res = await fetch(config.bulkUpdateUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Daily-Board': '1',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ items: targetRows.map(collectRow) }),
      });

      const data = await res.json().catch(() => ({}));
      const ok = res.ok && data.ok !== false;

      targetRows.forEach((row) => {
        row.classList.remove('is-saving');
        row.classList.add(ok ? 'is-saved' : 'is-error');
      });

      if (!ok) {
        setStatus(failedMessage(targetRows, data), 'danger', 3000);
        return false;
      }

      setStatus(savedMessage(targetRows, data), 'success', 3000);
      if (window.dailyShiftBoardCalendar) {
        window.dailyShiftBoardCalendar.refetchEvents();
      }
      setTimeout(() => {
        targetRows.forEach((row) => row.classList.remove('is-saved'));
      }, 1800);
      return true;
    } catch (err) {
      console.error(err);
      targetRows.forEach((row) => {
        row.classList.remove('is-saving');
        row.classList.add('is-error');
      });
      setStatus('Network error.', 'danger', 3000);
      return false;
    }
  }

  document.querySelectorAll('.js-daily-event-row').forEach((row) => recalcTotal(row));

  document.addEventListener('input', (e) => {
    if (!e.target.classList.contains('js-dsb-time')) return;
    const row = e.target.closest('.js-daily-event-row');
    if (row) recalcTotal(row, true);
  });

  document.addEventListener('click', (e) => {
    const rowSave = e.target.closest('.js-dsb-row-save');
    if (rowSave) {
      const row = rowSave.closest('.js-daily-event-row');
      saveRows([row]);
      return;
    }

    if (e.target.id === 'dailyBoardBulkSave') {
      saveRows(document.querySelectorAll('.js-daily-event-row'));
    }
  });

  document.addEventListener('change', (e) => {
    if (!e.target.classList.contains('js-dsb-status')) return;
    const row = e.target.closest('.js-daily-event-row');
    if (row) saveRows([row]);
  });

  function renderSubSummary(events) {
    const countEl = document.getElementById('dailySubCount');
    const summaryEl = document.getElementById('dailySubSummary');
    if (!summaryEl) return;

    const subEvent = (events || []).find((event) => {
      const props = event.extendedProps || {};
      const classes = event.classNames || [];
      return props.category === 'subcount' || classes.includes('ev-subcount');
    });

    summaryEl.innerHTML = '';

    if (!subEvent) {
      if (countEl) countEl.textContent = '0';
      const empty = document.createElement('div');
      empty.className = 'text-muted small';
      empty.textContent = 'No SUB candidate data for this date.';
      summaryEl.appendChild(empty);
      return;
    }

    const props = subEvent.extendedProps || {};
    const breakdown = props.count_breakdown || {};
    const count = Number.isFinite(props.count)
      ? props.count
      : (Number(breakdown.line || 0) + Number(breakdown.work_instead || 0) + Number(breakdown.subs || 0));

    if (countEl) countEl.textContent = String(count);

    const meta = document.createElement('div');
    meta.className = 'small text-muted mb-2';
    meta.textContent = `Regular: ${breakdown.line || 0} / RWD: ${breakdown.work_instead || 0} / Extra: ${breakdown.subs || 0}`;
    summaryEl.appendChild(meta);

    const addGroup = (title, list, absent = false) => {
      const group = document.createElement('div');
      group.className = 'dsb-sub-group';

      const heading = document.createElement('div');
      heading.className = 'dsb-sub-group-title';
      heading.textContent = title;
      group.appendChild(heading);

      if (!list || !list.length) {
        const none = document.createElement('span');
        none.className = 'text-muted small';
        none.textContent = '-';
        group.appendChild(none);
      } else {
        list.forEach((item) => {
          const pill = document.createElement('span');
          pill.className = absent ? 'dsb-sub-pill dsb-sub-pill--absent' : 'dsb-sub-pill';

          const name = document.createElement('span');
          name.textContent = item?.name || String(item || '');
          pill.appendChild(name);

          if (item?.start_hm || item?.end_hm) {
            const time = document.createElement('span');
            time.className = 'dsb-sub-time';
            time.textContent = `${item.start_hm || ''}${item.start_hm && item.end_hm ? '-' : ''}${item.end_hm || ''}`;
            pill.appendChild(time);
          }

          group.appendChild(pill);
        });
      }

      summaryEl.appendChild(group);
    };

    const users = props.users || {};
    const absentUsers = props.absent_users || {};
    addGroup('Available Regular Subs', users.line || []);
    addGroup('Available Rostered Working Day', users.work_instead || []);
    addGroup('Available Extra Subs', users.subs || []);
    addGroup('Absence Regular Subs', absentUsers.line || [], true);
    addGroup('Absence Rostered Working Day', absentUsers.work_instead || [], true);
    addGroup('Absence Extra Subs', absentUsers.subs || [], true);
  }

  function appendText(parent, tag, text, className = '') {
    const el = document.createElement(tag);
    if (className) el.className = className;
    el.textContent = text;
    parent.appendChild(el);
    return el;
  }

  function appendMeta(parent, label, value) {
    if (value == null || value === '') return;
    const item = document.createElement('div');
    item.className = 'dsb-modal-meta-item';
    appendText(item, 'span', label, 'dsb-modal-meta-label');
    appendText(item, 'span', value, 'dsb-modal-meta-value');
    parent.appendChild(item);
  }

  function openDailyEventModal(event) {
    const props = event.extendedProps || {};
    if (props.category !== 'event') return;

    const titleEl = document.getElementById('dailyEventModalTitle');
    const bodyEl = document.getElementById('dailyEventModalBody');
    const modalEl = document.getElementById('dailyEventModal');
    if (!bodyEl || !modalEl) return;

    if (titleEl) {
      titleEl.textContent = event.title || 'Shift Details';
    }

    bodyEl.innerHTML = '';

    const meta = document.createElement('div');
    meta.className = 'dsb-modal-meta';
    appendMeta(meta, 'Date', props.event_date || (event.start ? event.start.toISOString().slice(0, 10) : ''));
    appendMeta(meta, 'Original', props.original_user_name || '');
    appendMeta(meta, 'Assigned', props.assigned_user_name || (props.assigned_user_id != null ? `#${props.assigned_user_id}` : ''));
    appendMeta(meta, 'School', props.school_name || props.school || '');
    appendMeta(meta, 'Time', props.start_time && props.end_time ? `${props.start_time}-${props.end_time}` : (props.start_time || props.end_time || ''));
    bodyEl.appendChild(meta);

    appendText(bodyEl, 'div', 'Class Details', 'dsb-modal-section-title');

    const details = Array.isArray(props.details) ? props.details : [];
    if (!details.length) {
      appendText(bodyEl, 'div', 'No class details.', 'text-muted small');
    } else {
      const list = document.createElement('div');
      list.className = 'dsb-class-detail-list';

      details.forEach((detail) => {
        const row = document.createElement('div');
        row.className = 'dsb-class-detail-row';

        const start = detail?.start_hm || '';
        const minutes = detail?.lesson_min != null ? Number(detail.lesson_min) : null;
        const startMin = hhmmToMin(start);
        const end = startMin != null && minutes != null ? minToHhmm(startMin + minutes) : '';
        const range = start && end ? `${start}-${end}` : (start || '-');

        appendText(row, 'span', range, 'dsb-class-detail-time');
        appendText(row, 'span', detail?.lesson_code || '-', 'dsb-class-detail-code');
        appendText(row, 'span', detail?.lesson_name || '-', 'dsb-class-detail-name');
        appendText(row, 'span', minutes != null ? `${minutes} min` : (detail?.lesson_type || '-'), 'dsb-class-detail-min');

        list.appendChild(row);
      });

      bodyEl.appendChild(list);
    }

    if (props.notes) {
      appendText(bodyEl, 'div', 'Notes', 'dsb-modal-section-title');
      const notes = document.createElement('div');
      notes.className = 'dsb-modal-notes';
      notes.textContent = props.notes;
      bodyEl.appendChild(notes);
    }

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  const calendarEl = document.getElementById('dailyCalendar');
  if (calendarEl && window.FullCalendar) {
    function dailyEventContent(arg) {
      const event = arg.event;
      const props = event.extendedProps || {};
      const wrap = document.createElement('div');
      wrap.className = 'dsb-fc-event';

      const title = document.createElement('div');
      title.className = 'dsb-fc-event-title';
      title.textContent = event.title || '';
      wrap.appendChild(title);

      if (props.category === 'event') {
        const original = props.original_user_name || null;
        const assigned = props.assigned_user_name || (props.assigned_user_id != null ? `#${props.assigned_user_id}` : null);
        const metaParts = [];

        if (original) metaParts.push(`Original: ${original}`);
        if (assigned) metaParts.push(`Assigned: ${assigned}`);

        if (metaParts.length) {
          const meta = document.createElement('div');
          meta.className = 'dsb-fc-event-meta';
          meta.textContent = metaParts.join(' / ');
          wrap.appendChild(meta);
        }

        if (Array.isArray(props.details) && props.details.length) {
          const codes = [...new Set(props.details.map((detail) => detail?.lesson_code).filter(Boolean))];
          if (codes.length) {
            const codeWrap = document.createElement('div');
            codeWrap.className = 'dsb-fc-event-codes';
            codes.forEach((code) => {
              const pill = document.createElement('span');
              pill.className = 'dsb-fc-code';
              pill.textContent = code;
              codeWrap.appendChild(pill);
            });
            wrap.appendChild(codeWrap);
          }
        }
      }

      return { domNodes: [wrap] };
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
      locale: 'en',
      initialView: 'listDay',
      initialDate: selectedDate,
      height: '100%',
      displayEventTime: false,
      headerToolbar: { left: '', center: 'title', right: '' },
      noEventsContent: 'No forecast events.',
      eventContent: dailyEventContent,
      events(fetchInfo, successCallback, failureCallback) {
        const url = new URL(config.eventsUrl, window.location.origin);
        url.searchParams.set('start', fetchInfo.startStr);
        url.searchParams.set('end', fetchInfo.endStr);

        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
          .then((res) => {
            if (!res.ok) throw new Error('Failed to load events.');
            return res.json();
          })
          .then((events) => {
            renderSubSummary(events);
            successCallback(events);
          })
          .catch((err) => {
            console.error(err);
            renderSubSummary([]);
            failureCallback(err);
          });
      },
      eventClick(info) {
        info.jsEvent?.preventDefault();
        openDailyEventModal(info.event);
      },
    });
    calendar.render();
    window.dailyShiftBoardCalendar = calendar;
  }

  function setupResizeHandle(handle) {
    const targetId = handle.dataset.target;
    const storageKey = handle.dataset.storageKey;
    const target = document.getElementById(targetId);
    if (!target) return;
    const linkedTargets = targetId === 'dailyCalendarPane'
      ? [document.getElementById('dailySubSummary')].filter(Boolean)
      : [];

    function applyHeight(height) {
      const px = `${Math.round(height)}px`;
      target.style.height = px;
      linkedTargets.forEach((el) => {
        el.style.height = px;
      });
      if (targetId === 'dailyCalendarPane' && window.dailyShiftBoardCalendar) {
        window.dailyShiftBoardCalendar.updateSize();
      }
    }

    const saved = Number(localStorage.getItem(storageKey));
    if (saved) {
      applyHeight(Math.max(180, Math.min(saved, window.innerHeight * 0.78)));
    }

    handle.addEventListener('mousedown', (e) => {
      e.preventDefault();
      const startY = e.clientY;
      const startHeight = target.getBoundingClientRect().height;

      const onMove = (ev) => {
        const next = Math.max(180, Math.min(startHeight + ev.clientY - startY, window.innerHeight * 0.78));
        applyHeight(next);
      };

      const onUp = () => {
        localStorage.setItem(storageKey, String(Math.round(target.getBoundingClientRect().height)));
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
      };

      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  }

  document.querySelectorAll('.dsb-resize-handle').forEach(setupResizeHandle);
});
