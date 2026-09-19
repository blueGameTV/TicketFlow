(() => {
  const root = document.querySelector('[data-global-search]');
  const input = document.getElementById('global-search-input');
  const results = document.getElementById('global-search-results');
  if (!root || !input || !results) return;

  let timer = null;
  let controller = null;
  let mobileOpen = false;

  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));

  const hide = () => {
    results.hidden = true;
    results.innerHTML = '';
  };

  const section = (title, icon, items, renderer) => {
    if (!items.length) return '';
    return `<section class="global-search-section-v015"><header><i class="fa-solid ${icon}"></i><span>${title}</span><small>${items.length}</small></header>${items.map(renderer).join('')}</section>`;
  };

  const render = (data) => {
    const ticketHtml = section('Tickets', 'fa-ticket', data.tickets || [], (item) => `
      <a class="global-search-item-v015" href="${esc(item.url)}">
        <span class="global-search-item-icon-v015"><i class="fa-solid fa-ticket-simple"></i></span>
        <span class="global-search-item-copy-v015"><strong>${esc(item.number)} — ${esc(item.title)}</strong><small>${esc(item.requester)} · ${esc(item.status)} · ${esc(item.priority)}</small></span>
        <i class="fa-solid fa-chevron-right"></i>
      </a>`);

    const userHtml = section('Utilisateurs', 'fa-users', data.users || [], (item) => {
      const tag = item.url ? 'a' : 'div';
      const href = item.url ? ` href="${esc(item.url)}"` : '';
      return `<${tag} class="global-search-item-v015"${href}>
        <span class="global-search-item-icon-v015"><i class="fa-solid fa-user"></i></span>
        <span class="global-search-item-copy-v015"><strong>${esc(item.name)}</strong><small>@${esc(item.username)} · ${esc(item.role)} · ${esc(item.email)}</small></span>
        ${item.url ? '<i class="fa-solid fa-chevron-right"></i>' : ''}
      </${tag}>`;
    });

    const groupHtml = section('Groupes', 'fa-people-group', data.groups || [], (item) => {
      const tag = item.url ? 'a' : 'div';
      const href = item.url ? ` href="${esc(item.url)}"` : '';
      return `<${tag} class="global-search-item-v015"${href}>
        <span class="global-search-item-icon-v015"><i class="fa-solid fa-people-group"></i></span>
        <span class="global-search-item-copy-v015"><strong>${esc(item.name)}</strong><small>${esc(item.manager)} · ${Number(item.members)} membre${Number(item.members) > 1 ? 's' : ''}</small></span>
        ${item.url ? '<i class="fa-solid fa-chevron-right"></i>' : ''}
      </${tag}>`;
    });

    const html = ticketHtml + userHtml + groupHtml;
    results.innerHTML = html || '<div class="global-search-empty-v015"><i class="fa-regular fa-face-frown"></i><strong>Aucun résultat</strong><span>Essayez un numéro de ticket, un nom ou un groupe.</span></div>';
    results.hidden = false;
  };

  const search = async () => {
    const q = input.value.trim();
    if (q.length < 2) {
      hide();
      return;
    }
    controller?.abort();
    controller = new AbortController();
    results.hidden = false;
    results.innerHTML = '<div class="global-search-loading-v015"><i class="fa-solid fa-spinner fa-spin"></i> Recherche…</div>';
    try {
      const response = await fetch(`api/global-search.php?q=${encodeURIComponent(q)}&_=${Date.now()}`, {
        cache: 'no-store',
        headers: {Accept: 'application/json'},
        signal: controller.signal,
      });
      if (!response.ok) throw new Error('search_failed');
      const data = await response.json();
      if (!data.ok) throw new Error('search_failed');
      render(data);
    } catch (error) {
      if (error.name === 'AbortError') return;
      results.innerHTML = '<div class="global-search-empty-v015"><i class="fa-solid fa-triangle-exclamation"></i><strong>Recherche indisponible</strong><span>Réessayez dans quelques instants.</span></div>';
      results.hidden = false;
    }
  };

  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(search, 280);
  });
  input.addEventListener('focus', () => {
    if (input.value.trim().length >= 2) search();
  });

  document.addEventListener('click', (event) => {
    if (!root.contains(event.target)) hide();
  });
  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      input.focus();
      input.select();
    }
    if (event.key === 'Escape') {
      hide();
      input.blur();
    }
  });

  const toggle = root.querySelector('[data-global-search-toggle]');
  toggle?.addEventListener('click', () => {
    mobileOpen = !mobileOpen;
    root.classList.toggle('is-mobile-open', mobileOpen);
    if (mobileOpen) input.focus();
    else hide();
  });
})();

(() => {
  const toggle = document.querySelector('[data-advanced-filter-toggle]');
  const panel = document.querySelector('[data-advanced-filter-panel]');
  if (!toggle || !panel) return;
  const sync = () => {
    const opened = !panel.hidden;
    toggle.classList.toggle('active', opened);
    toggle.innerHTML = `<i class="fa-solid fa-sliders"></i> ${opened ? 'Masquer les filtres' : 'Filtres avancés'}`;
  };
  toggle.addEventListener('click', () => {
    panel.hidden = !panel.hidden;
    sync();
  });
  sync();
})();
