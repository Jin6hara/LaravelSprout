(function () {
  const ACTION_DETACH = 'detach';
  const ACTION_DELETE = 'delete';

  function generatedShiftsFromSource(sourceId) {
    const source = sourceId ? document.getElementById(sourceId) : null;
    return source ? JSON.parse(source.textContent || '[]') : [];
  }

  function renderCards(wrap, shifts) {
    wrap.innerHTML = '';

    shifts.forEach((shift) => {
      const card = document.createElement('div');
      card.className = 'generated-shift-card';

      const title = document.createElement('div');
      title.className = 'fw-semibold mb-2';
      title.textContent = `Generated Shift #${shift.id || '-'}`;
      card.appendChild(title);

      const meta = document.createElement('div');
      meta.className = 'generated-shift-meta';
      [
        ['Leave ID', 'leave_id'],
        ['Date', 'date'],
        ['Original', 'original'],
        ['Assigned', 'assigned'],
        ['School', 'school'],
        ['Time', 'time'],
        ['Status', 'status'],
      ].forEach(([label, key]) => {
        const field = document.createElement('div');
        field.className = 'generated-shift-field';

        const labelEl = document.createElement('span');
        labelEl.className = 'generated-shift-label';
        labelEl.textContent = label;

        const valueEl = document.createElement('div');
        valueEl.className = 'generated-shift-value';
        valueEl.textContent = shift[key] || '-';

        field.append(labelEl, valueEl);
        meta.appendChild(field);
      });
      card.appendChild(meta);

      ['lesson', 'notes'].forEach((key) => {
        const wide = document.createElement('div');
        wide.className = 'generated-shift-wide';

        const labelEl = document.createElement('span');
        labelEl.className = 'generated-shift-label';
        labelEl.textContent = key === 'lesson' ? 'Lesson' : 'Notes';

        const valueEl = document.createElement('div');
        valueEl.className = 'generated-shift-value';
        valueEl.textContent = shift[key] || '-';

        wide.append(labelEl, valueEl);
        card.appendChild(wide);
      });

      wrap.appendChild(card);
    });
  }

  function open(options) {
    const modalEl = document.getElementById(options.modalId);
    if (!modalEl) return;

    const shifts = options.shifts || [];
    const text = modalEl.querySelector('[data-generated-shift-text]');
    const summary = modalEl.querySelector('[data-generated-shift-summary]');
    const list = modalEl.querySelector('[data-generated-shift-list]');
    const keepBtn = modalEl.querySelector('[data-generated-shift-keep]');
    const deleteBtn = modalEl.querySelector('[data-generated-shift-delete]');

    if (text) text.textContent = options.text || '';
    if (summary) summary.textContent = options.summary || '';

    if (list) {
      if (shifts.length > 0) {
        list.style.display = '';
        renderCards(list, shifts);
      } else {
        list.innerHTML = '';
        list.style.display = 'none';
      }
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    if (keepBtn) {
      keepBtn.onclick = () => {
        options.onKeep?.();
        modal.hide();
      };
    }

    if (deleteBtn) {
      deleteBtn.onclick = () => {
        options.onDelete?.();
        modal.hide();
      };
    }
  }

  function bindInactiveStatusForm(options) {
    document.addEventListener('submit', (event) => {
      const form = event.target.closest(options.formSelector);
      if (!form) return;

      const actionInput = form.querySelector(`input[name="${options.actionInputName || 'generated_shift_action'}"]`);
      if (actionInput?.value) return;

      const status = form.querySelector(options.statusSelector || 'select[name="status"]')?.value || '';
      const inactiveStatuses = options.inactiveStatuses || ['rejected', 'cancelled'];
      if (!inactiveStatuses.includes(status)) return;

      const sourceId = form.querySelector(options.sourceSelector || '[data-shifts-source]')?.dataset.shiftsSource;
      const shifts = generatedShiftsFromSource(sourceId);
      if (shifts.length === 0) return;

      event.preventDefault();

      open({
        modalId: options.modalId,
        shifts,
        text: options.textBuilder ? options.textBuilder({ status, shifts, form }) : `Please choose what to do with generated shift(s).`,
        summary: options.summaryBuilder ? options.summaryBuilder({ status, shifts, form }) : `${shifts.length} generated shift(s) are linked.`,
        onKeep: () => {
          actionInput.value = ACTION_DETACH;
          form.submit();
        },
        onDelete: () => {
          actionInput.value = ACTION_DELETE;
          form.submit();
        },
      });
    });
  }

  function bindApprovalDenyForm(options) {
    document.addEventListener('submit', (event) => {
      const form = event.target.closest(options.formSelector);
      if (!form) return;

      const actionInput = form.querySelector(`input[name="${options.actionInputName || 'generated_shift_action'}"]`);
      if (actionInput?.value) return;

      const shifts = generatedShiftsFromSource(options.sourceId);
      if (shifts.length === 0) return;

      event.preventDefault();

      open({
        modalId: options.modalId,
        shifts,
        text: options.text || 'Please choose what to do with generated shift(s).',
        summary: options.summaryBuilder ? options.summaryBuilder({ shifts, form }) : `${shifts.length} generated shift(s) are linked.`,
        onKeep: () => {
          actionInput.value = ACTION_DETACH;
          form.submit();
        },
        onDelete: () => {
          actionInput.value = ACTION_DELETE;
          form.submit();
        },
      });
    });
  }

  window.GeneratedShiftConfirmation = {
    ACTION_DETACH,
    ACTION_DELETE,
    generatedShiftsFromSource,
    renderCards,
    open,
    bindInactiveStatusForm,
    bindApprovalDenyForm,
  };
})();
