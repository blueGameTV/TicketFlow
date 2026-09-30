(() => {
  const select = document.querySelector('[data-theme-preference]');
  if (!select) return;
  const allowed = new Set(['light','dark','system']);
  select.addEventListener('change', () => {
    const value = allowed.has(select.value) ? select.value : 'system';
    document.documentElement.setAttribute('data-theme', value);
  });
})();
