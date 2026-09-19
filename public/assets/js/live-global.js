(() => {
    'use strict';

    async function refreshNotifications() {
        try {
            const response = await fetch('api/live-notifications.php', {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            });
            if (!response.ok) return;

            const data = await response.json();
            if (!data.ok) return;

            const badge = document.getElementById('live-notification-count');
            if (!badge) return;

            if (Number(data.unread) > 0) {
                badge.textContent = Number(data.unread) > 99 ? '99+' : String(data.unread);
                badge.classList.remove('is-hidden');
            } else {
                badge.textContent = '0';
                badge.classList.add('is-hidden');
            }
        } catch (_) {
            // Une panne de rafraîchissement ne doit jamais bloquer l'interface.
        }
    }

    refreshNotifications();
    window.setInterval(refreshNotifications, 1500);
})();
