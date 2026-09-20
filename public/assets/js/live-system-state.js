(() => {
    'use strict';

    const container = document.getElementById('global-service-banners');
    const modal = document.getElementById('v110-system-modal');
    const modalKind = document.getElementById('v110-system-modal-kind');
    const modalTitle = document.getElementById('v110-system-modal-title');
    const modalService = document.getElementById('v110-system-modal-service');
    const modalMessage = document.getElementById('v110-system-modal-message');
    const modalMeta = document.getElementById('v110-system-modal-meta');

    if (!container) return;

    const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[c]));

    let lastSignature = '';

    function openDetail(button) {
        if (!modal) return;
        modalKind.textContent = button.dataset.detailKind || 'Information';
        modalTitle.textContent = button.dataset.detailTitle || '';
        modalService.textContent = button.dataset.detailService || '';
        modalMessage.textContent = button.dataset.detailMessage || '';
        modalMeta.textContent = button.dataset.detailMeta || '';
        modal.hidden = false;
        document.body.classList.add('v110-modal-open');
        const close = modal.querySelector('.v110-system-modal-close');
        if (close) close.focus();
    }

    function closeDetail() {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('v110-modal-open');
    }

    document.addEventListener('click', (event) => {
        const detail = event.target.closest('[data-system-detail]');
        if (detail) {
            openDetail(detail);
            return;
        }
        if (event.target.closest('[data-system-modal-close]')) closeDetail();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) closeDetail();
    });

    function render(data) {
        const items = [];
        for (const a of (data.alerts || [])) {
            const icon = a.severity === 'critical' ? 'fa-triangle-exclamation' : 'fa-circle-info';
            items.push(
                `<button type="button" class="v110-service-banner v110-service-banner-button severity-${esc(a.severity)}" ` +
                `data-system-detail data-detail-kind="Alerte de service" ` +
                `data-detail-title="${esc(a.title)}" data-detail-service="${esc(a.service_name)}" ` +
                `data-detail-message="${esc(a.message)}" data-detail-meta="${esc(a.detail_meta)}">` +
                `<div class="v110-service-banner-icon"><i class="fa-solid ${icon}"></i></div>` +
                `<div class="v110-service-banner-copy"><span>${esc(a.service_name)}</span><strong>${esc(a.title)}</strong></div>` +
                `<span class="v110-service-banner-meta">${esc(a.severity_label)} <i class="fa-solid fa-chevron-right"></i></span>` +
                `</button>`
            );
        }
        for (const m of (data.maintenance || [])) {
            items.push(
                `<button type="button" class="v110-service-banner v110-service-banner-button severity-maintenance" ` +
                `data-system-detail data-detail-kind="Maintenance planifiée" data-detail-title="${esc(m.title)}" ` +
                `data-detail-service="TicketFlow" data-detail-message="${esc(m.message)}" data-detail-meta="${esc(m.period)}">` +
                `<div class="v110-service-banner-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>` +
                `<div class="v110-service-banner-copy"><span>Maintenance planifiée</span><strong>${esc(m.title)}</strong></div>` +
                `<span class="v110-service-banner-meta">${esc(m.short_period)} <i class="fa-solid fa-chevron-right"></i></span>` +
                `</button>`
            );
        }
        container.innerHTML = items.join('');
    }

    async function refresh() {
        try {
            const r = await fetch('api/live-system-state.php', {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!r.ok) return;
            const data = await r.json();
            if (!data.ok) return;
            if (data.access_blocked) {
                window.location.replace(data.redirect || 'maintenance.php');
                return;
            }
            const sig = JSON.stringify([data.alerts, data.maintenance]);
            if (sig !== lastSignature) {
                lastSignature = sig;
                render(data);
            }
        } catch (_) {}
    }

    refresh();
    window.setInterval(refresh, 2000);
})();
