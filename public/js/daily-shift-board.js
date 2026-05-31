document.addEventListener('DOMContentLoaded', function () {
  const config = window.dailyShiftBoard || {};
  const selectedDate = config.selectedDate;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    || document.querySelector('input[name="_token"]')?.value;
  const statusEl = document.getElementById('dailyBoardSaveStatus');

  function setStatus(message, kind = 'muted') {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.className = `small text-${kind}`;
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
      setStatus('No rows to save.', 'warning');
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
        setStatus(data.message || 'Save failed.', 'danger');
        return false;
      }

      setStatus(data.message || 'Saved.', 'success');
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
      setStatus('Network error.', 'danger');
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

  const calendarEl = document.getElementById('dailyCalendar');
  if (calendarEl && window.FullCalendar) {
    const calendar = new FullCalendar.Calendar(calendarEl, {
      locale: 'en',
      initialView: 'listDay',
      initialDate: selectedDate,
      height: '100%',
      displayEventTime: false,
      headerToolbar: { left: '', center: 'title', right: '' },
      noEventsContent: 'No forecast events.',
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
