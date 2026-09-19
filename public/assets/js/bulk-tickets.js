(() => {
  const form = document.querySelector('[data-bulk-ticket-form]');
  if (!form) return;

  const checkboxes = [...form.querySelectorAll('[data-ticket-checkbox]')];
  const selectAll = form.querySelector('[data-select-all-tickets]');
  const count = form.querySelector('[data-bulk-count]');
  const action = form.querySelector('[data-bulk-action]');
  const submit = form.querySelector('[data-bulk-submit]');
  const fields = [...form.querySelectorAll('[data-bulk-field]')];

  const selectedCount = () => checkboxes.filter((box) => box.checked).length;

  const updateSelection = () => {
    const selected = selectedCount();
    if (count) count.textContent = `${selected} sélectionné${selected > 1 ? 's' : ''}`;
    if (selectAll) {
      selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
      selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
    }
    checkboxes.forEach((box) => {
      box.closest('.ticket-queue-item')?.classList.toggle('is-selected-v0152', box.checked);
    });
    if (submit) submit.disabled = selected === 0 || !action?.value;
  };

  const updateAction = () => {
    const value = action?.value || '';
    fields.forEach((field) => {
      const active = field.dataset.bulkField === value;
      field.hidden = !active;
      field.disabled = !active;
      if (!active) field.value = '';
    });
    updateSelection();
  };

  selectAll?.addEventListener('change', () => {
    checkboxes.forEach((box) => { box.checked = selectAll.checked; });
    updateSelection();
  });
  checkboxes.forEach((box) => box.addEventListener('change', updateSelection));
  action?.addEventListener('change', updateAction);

  form.addEventListener('submit', (event) => {
    const selected = selectedCount();
    if (selected === 0) {
      event.preventDefault();
      return;
    }
    const actionLabel = action?.selectedOptions?.[0]?.textContent?.trim() || 'cette action';
    if (!window.confirm(`Appliquer « ${actionLabel} » à ${selected} ticket${selected > 1 ? 's' : ''} ?`)) {
      event.preventDefault();
    }
  });

  updateAction();
})();
