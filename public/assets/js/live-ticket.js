(() => {
  const number = window.TICKETFLOW_TICKET_NUMBER;
  const currentUserId = Number(window.TICKETFLOW_CURRENT_USER_ID || 0);
  if (!number) return;

  let lastStateHash = null;
  let refreshInProgress = false;
  let actionInProgress = false;
  let timer = null;

  const esc = (s) => String(s ?? '').replace(/[&<>'"]/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
  }[c]));

  const toast = (message, ok = true) => {
    const el = document.getElementById('live-toast');
    if (!el) return;
    el.hidden = false;
    el.className = 'live-toast ' + (ok ? 'success' : 'error');
    el.textContent = message;
    clearTimeout(el._ticketflowTimer);
    el._ticketflowTimer = setTimeout(() => { el.hidden = true; }, 3500);
  };

  const roleIcon = (role, own) => {
    if (own) return 'fa-user';
    if (role === 'Manager') return 'fa-user-tie';
    if (role === 'IT' || role === 'Administrateur') return 'fa-headset';
    return 'fa-user';
  };

  const renderMessages = (items) => {
    const box = document.getElementById('live-conversation');
    if (!box) return;

    box.innerHTML = items.length
      ? ''
      : '<p class="empty-state">Aucun message pour le moment.</p>';

    for (const message of items) {
      const own = Number(message.author_id || 0) === currentUserId;
      const article = document.createElement('article');
      article.className = 'chat-message-v0136 ' + (own ? 'is-own ' : 'is-other ') + (Number(message.internal) ? 'internal-note' : '');
      article.innerHTML = `
        <div class="chat-message-avatar-v0136" aria-hidden="true"><i class="fa-solid ${roleIcon(String(message.author_role || ''), own)}"></i></div>
        <div class="chat-message-bubble-v0136">
          <div class="chat-message-meta-v0136">
            <div>
              <strong>${esc(message.author_name)}</strong>
              <span>${esc(message.author_role)}</span>
              ${Number(message.internal) ? ' <span class="internal-badge">Interne IT</span>' : ''}
            </div>
            <time>${new Date(String(message.created_at).replace(' ', 'T')).toLocaleString('fr-FR', {dateStyle: 'short', timeStyle: 'short'})}</time>
          </div>
          <div class="chat-message-text-v0136">${esc(message.message).replace(/\n/g, '<br>')}</div>
        </div>`;
      box.appendChild(article);
    }

    const count = document.getElementById('live-message-count');
    if (count) count.textContent = `${items.length} message${items.length > 1 ? 's' : ''}`;
  };

  const updateLightweightState = (data) => {
    const ticket = data.ticket;

    const statusBadge = document.getElementById('live-ticket-status');
    if (statusBadge) {
      statusBadge.textContent = ticket.status_name;
      statusBadge.className = `ticket-status status-${ticket.status_code}`;
    }

    const statusText = document.getElementById('live-ticket-status-text');
    if (statusText) statusText.textContent = ticket.status_name;

    const priority = document.getElementById('live-ticket-priority');
    if (priority) {
      priority.textContent = ticket.priority_name;
      priority.className = `priority-pill priority-level-${ticket.priority_level}`;
    }

    const assignee = document.getElementById('live-ticket-assignee');
    if (assignee) assignee.textContent = ticket.assigned_it_name;

    renderMessages(data.messages || []);
  };

  const captureDrafts = () => {
    const drafts = [];
    document.querySelectorAll('#live-ticket-root textarea').forEach((field, index) => {
      if (field.value !== '') {
        drafts.push({
          kind: 'textarea',
          name: field.name,
          index,
          value: field.value,
          focused: document.activeElement === field,
          selectionStart: field.selectionStart,
          selectionEnd: field.selectionEnd,
        });
      }
    });
    return drafts;
  };

  const restoreDrafts = (drafts) => {
    const fields = [...document.querySelectorAll('#live-ticket-root textarea')];
    for (const draft of drafts) {
      let field = null;
      if (draft.name) {
        const sameName = fields.filter((el) => el.name === draft.name);
        field = sameName[0] || null;
      }
      if (!field) field = fields[draft.index] || null;
      if (!field) continue;
      field.value = draft.value;
      if (draft.focused) {
        field.focus({preventScroll: true});
        try { field.setSelectionRange(draft.selectionStart, draft.selectionEnd); } catch (_) {}
      }
    }
  };

  const hasPendingFileSelection = () =>
    [...document.querySelectorAll('#live-ticket-root input[type="file"]')]
      .some((input) => input.files && input.files.length > 0);

  const refreshWholeTicketFragment = async () => {
    if (refreshInProgress || actionInProgress || hasPendingFileSelection()) return;
    refreshInProgress = true;
    const drafts = captureDrafts();

    try {
      const url = `ticket.php?number=${encodeURIComponent(number)}&fragment=1&_=${Date.now()}`;
      const response = await fetch(url, {
        cache: 'no-store',
        headers: {'X-Requested-With': 'fetch-fragment'}
      });
      if (!response.ok) return;

      const html = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const freshRoot = doc.querySelector('#live-ticket-root');
      const currentRoot = document.querySelector('#live-ticket-root');
      if (!freshRoot || !currentRoot) return;

      currentRoot.replaceWith(freshRoot);
      restoreDrafts(drafts);
    } catch (_) {
      // Le polling réessaiera automatiquement au prochain cycle.
    } finally {
      refreshInProgress = false;
    }
  };

  const refresh = async (forceFragment = false) => {
    if (actionInProgress) return;
    try {
      const response = await fetch(`api/ticket-state.php?number=${encodeURIComponent(number)}&_=${Date.now()}`, {
        cache: 'no-store',
        headers: {Accept: 'application/json'}
      });
      if (!response.ok) return;
      const data = await response.json();
      if (!data.ok) return;

      updateLightweightState(data);

      const changed = lastStateHash !== null && data.state_hash !== lastStateHash;
      lastStateHash = data.state_hash;

      if (forceFragment || changed) {
        await refreshWholeTicketFragment();
      }
    } catch (_) {
      // Une erreur réseau temporaire ne doit pas casser la page.
    }
  };

  const schedule = () => {
    clearTimeout(timer);
    const delay = document.hidden ? 4000 : 1200;
    timer = setTimeout(async () => {
      await refresh(false);
      schedule();
    }, delay);
  };

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) refresh(true);
    schedule();
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.closest('#live-ticket-root')) return;
    if (!form.querySelector('input[name="action"]')) return;

    event.preventDefault();
    const button = event.submitter;
    if (button) button.disabled = true;
    actionInProgress = true;

    try {
      const data = new FormData(form);
      if (button?.name) data.set(button.name, button.value);

      const response = await fetch(location.href, {
        method: 'POST',
        body: data,
        headers: {
          'X-Requested-With': 'fetch',
          Accept: 'application/json'
        }
      });

      let payload;
      try {
        payload = await response.json();
      } catch (_) {
        throw new Error('Réponse serveur invalide.');
      }

      if (!response.ok || !payload.ok) {
        throw new Error(payload.message || 'Impossible d’enregistrer cette action.');
      }

      toast(payload.message || 'Action enregistrée.');

      if (data.get('action') === 'message') {
        const textarea = form.querySelector('textarea[name="message"]');
        if (textarea) textarea.value = '';
      }
      if (data.get('action') === 'attachment_upload') {
        form.reset();
      }

      actionInProgress = false;
      await refresh(true);
    } catch (error) {
      toast(error?.message || 'Une erreur est survenue.', false);
    } finally {
      actionInProgress = false;
      if (button) button.disabled = false;
    }
  });

  refresh(false);
  schedule();
})();
