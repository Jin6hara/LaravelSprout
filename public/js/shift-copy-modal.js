(function () {
  const fieldNames = [
    'event_date',
    'title',
    'original_user_id',
    'Leave_type',
    'school_name',
    'start_time',
    'end_time',
    'total_duration',
    'Lesson',
    'assigned_user_id',
    'type',
    'status',
    'notes',
  ];

  function sourceValues(source) {
    const values = {};
    source.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
      if (!fieldNames.includes(field.name)) return;
      values[field.name] = field.value;
    });
    return values;
  }

  function fillCopyForm(form, values) {
    fieldNames.forEach((name) => {
      const field = form.querySelector(`[name="${name}"]`);
      if (!field) return;
      field.value = values[name] || '';
    });
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.js-shift-copy');
    if (!button) return;

    const modalEl = document.getElementById('shiftCopyModal');
    const form = document.getElementById('shiftCopyForm');
    if (!modalEl || !form || !window.bootstrap) return;

    const source = button.closest('.js-event-form, .js-daily-event-row');
    if (!source) return;

    form.action = button.dataset.store || form.action;
    fillCopyForm(form, sourceValues(source));

    const title = document.getElementById('shiftCopyModalTitle');
    const eventId = source.dataset.eventId;
    if (title) {
      title.textContent = eventId ? `Copy Shift #${eventId}` : 'Copy Shift';
    }

    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });
})();
