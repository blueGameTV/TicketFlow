(() => {
    'use strict';

    const body = document.body;
    const sidebar = document.getElementById('app-sidebar');
    const openButton = document.querySelector('[data-sidebar-open]');
    const closeButton = document.querySelector('[data-sidebar-close]');
    const overlay = document.querySelector('[data-sidebar-overlay]');

    const openSidebar = () => body.classList.add('sidebar-mobile-open');
    const closeSidebar = () => body.classList.remove('sidebar-mobile-open');

    openButton?.addEventListener('click', openSidebar);
    closeButton?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeSidebar();
    });

    sidebar?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 900px)').matches) closeSidebar();
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const inputId = button.getAttribute('data-password-toggle');
            const input = inputId ? document.getElementById(inputId) : null;
            if (!(input instanceof HTMLInputElement)) return;

            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !reveal);
                icon.classList.toggle('fa-eye-slash', reveal);
            }
            button.setAttribute('aria-label', reveal ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });

    // Rend les lignes de tableaux plus confortables sur mobile sans modifier le HTML métier.
    document.querySelectorAll('.table-wrap table').forEach((table) => {
        table.setAttribute('data-responsive-table', 'true');
    });
})();
