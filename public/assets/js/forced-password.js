(() => {
  const layer = document.querySelector('[data-forced-password-layer]');
  const form = document.querySelector('[data-forced-password-form]');
  if (!layer || !form) return;

  const newInput = form.querySelector('#forced-new-password');
  const confirmInput = form.querySelector('#forced-confirm-password');
  const submit = form.querySelector('button[type="submit"]');
  const feedback = form.querySelector('[data-forced-password-feedback]');
  const checks = [...form.querySelectorAll('[data-password-rule]')];

  const tests = {
    length: value => value.length >= 12,
    upper: value => /[A-Z]/.test(value),
    lower: value => /[a-z]/.test(value),
    digit: value => /\d/.test(value),
    match: () => confirmInput.value.length > 0 && newInput.value === confirmInput.value,
  };

  const refreshRules = () => {
    const value = newInput.value;
    checks.forEach(item => {
      const rule = item.dataset.passwordRule;
      const valid = rule === 'match' ? tests.match() : tests[rule]?.(value);
      item.classList.toggle('is-valid', Boolean(valid));
      item.classList.toggle('is-invalid', !valid && (value.length > 0 || confirmInput.value.length > 0));
      const icon = item.querySelector('i');
      if (icon) icon.className = valid ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
    });
  };

  const setFeedback = (message = '', type = '') => {
    feedback.textContent = message;
    feedback.className = 'tf-forced-password-feedback' + (type ? ` is-${type}` : '');
    feedback.hidden = message === '';
  };

  newInput.addEventListener('input', refreshRules);
  confirmInput.addEventListener('input', refreshRules);

  form.addEventListener('submit', async event => {
    event.preventDefault();
    refreshRules();
    setFeedback();
    submit.disabled = true;
    submit.classList.add('is-loading');

    try {
      const response = await fetch('api/forced-password.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: new FormData(form),
        credentials: 'same-origin',
      });
      const data = await response.json().catch(() => ({ok:false,message:'Réponse serveur invalide.'}));
      if (!response.ok || !data.ok) {
        setFeedback(data.message || 'Impossible de modifier le mot de passe.', 'error');
        return;
      }

      setFeedback(data.message || 'Mot de passe modifié avec succès.', 'success');
      layer.classList.add('is-success');
      setTimeout(() => window.location.replace('dashboard.php'), 650);
    } catch (error) {
      setFeedback('Impossible de contacter TicketFlow. Vérifiez votre connexion puis réessayez.', 'error');
    } finally {
      submit.disabled = false;
      submit.classList.remove('is-loading');
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  window.addEventListener('load', () => newInput.focus());
})();
