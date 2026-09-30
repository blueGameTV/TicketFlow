(() => {
  const password = document.getElementById('new_password');
  const confirm = document.getElementById('confirm_password');
  const box = document.querySelector('[data-password-strength]');
  const rulesBox = document.querySelector('[data-password-rules]');
  if (!password || !confirm || !box || !rulesBox) return;

  const label = box.querySelector('[data-strength-label]');
  const bars = [...box.querySelectorAll('.password-strength-track-v12061 span')];
  const isEnglish = (document.documentElement.lang || '').toLowerCase().startsWith('en');
  const labels = isEnglish
    ? ['Not rated', 'Weak', 'Fair', 'Strong', 'Very strong']
    : ['Non évalué', 'Faible', 'Moyen', 'Fort', 'Très fort'];

  const setRule = (name, valid, neutral = false) => {
    const el = rulesBox.querySelector(`[data-rule="${name}"]`);
    if (!el) return;
    el.classList.toggle('is-valid', valid && !neutral);
    el.classList.toggle('is-invalid', !valid && !neutral);
    const icon = el.querySelector('i');
    if (!icon) return;
    icon.className = neutral ? 'fa-regular fa-circle' : (valid ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark');
  };

  const refresh = () => {
    const value = password.value;
    const confirmValue = confirm.value;
    const checks = {
      length: value.length >= 12,
      upper: /[A-Z]/.test(value),
      lower: /[a-z]/.test(value),
      digit: /\d/.test(value),
      match: value.length > 0 && confirmValue.length > 0 && value === confirmValue,
    };

    setRule('length', checks.length, value.length === 0);
    setRule('upper', checks.upper, value.length === 0);
    setRule('lower', checks.lower, value.length === 0);
    setRule('digit', checks.digit, value.length === 0);
    setRule('match', checks.match, confirmValue.length === 0);

    let score = 0;
    if (value.length) {
      score = [checks.length, checks.upper, checks.lower, checks.digit].filter(Boolean).length;
      if (score === 4 && (value.length >= 16 || /[^A-Za-z0-9]/.test(value))) score = 4;
      else if (score === 4) score = 3;
      else score = Math.max(1, score);
    }

    box.dataset.strength = String(score);
    bars.forEach((bar, index) => bar.classList.toggle('is-active', index < score));
    label.textContent = labels[score];
  };

  password.addEventListener('input', refresh);
  confirm.addEventListener('input', refresh);
  refresh();
})();
