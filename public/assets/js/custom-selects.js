(() => {
    'use strict';

    const SELECTOR = '.app-content select:not([multiple]):not([size])';

    const closeAll = (except = null) => {
        document.querySelectorAll('.tf-select.is-open').forEach((wrapper) => {
            if (wrapper !== except) {
                wrapper.classList.remove('is-open');
                const button = wrapper.querySelector('.tf-select-button');
                if (button) button.setAttribute('aria-expanded', 'false');
            }
        });
    };

    const build = (select) => {
        if (!(select instanceof HTMLSelectElement) || select.dataset.tfSelectReady === '1') return;
        select.dataset.tfSelectReady = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'tf-select';
        if (select.disabled) wrapper.classList.add('is-disabled');

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'tf-select-button';
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');

        const value = document.createElement('span');
        value.className = 'tf-select-value';
        const chevron = document.createElement('i');
        chevron.className = 'fa-solid fa-chevron-down tf-select-chevron';
        button.append(value, chevron);

        const menu = document.createElement('div');
        menu.className = 'tf-select-menu';
        menu.setAttribute('role', 'listbox');

        const refresh = () => {
            const selected = select.options[select.selectedIndex];
            value.textContent = selected ? selected.textContent : '';
            wrapper.classList.toggle('is-disabled', select.disabled);
            button.disabled = select.disabled;
            menu.replaceChildren();

            Array.from(select.options).forEach((option, index) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'tf-select-option';
                item.setAttribute('role', 'option');
                item.dataset.value = option.value;
                item.dataset.index = String(index);
                item.textContent = option.textContent;
                item.disabled = option.disabled;
                if (option.selected) {
                    item.classList.add('is-selected');
                    item.setAttribute('aria-selected', 'true');
                } else {
                    item.setAttribute('aria-selected', 'false');
                }
                item.addEventListener('click', () => {
                    if (option.disabled) return;
                    select.selectedIndex = index;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    refresh();
                    wrapper.classList.remove('is-open');
                    button.setAttribute('aria-expanded', 'false');
                    button.focus();
                });
                menu.appendChild(item);
            });
        };

        button.addEventListener('click', () => {
            if (select.disabled) return;
            const willOpen = !wrapper.classList.contains('is-open');
            closeAll(wrapper);
            wrapper.classList.toggle('is-open', willOpen);
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) {
                const selected = menu.querySelector('.is-selected');
                if (selected) selected.scrollIntoView({ block: 'nearest' });
            }
        });

        button.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                wrapper.classList.remove('is-open');
                button.setAttribute('aria-expanded', 'false');
                return;
            }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                let index = select.selectedIndex;
                do {
                    index = Math.max(0, Math.min(select.options.length - 1, index + direction));
                } while (select.options[index] && select.options[index].disabled && index > 0 && index < select.options.length - 1);
                if (index !== select.selectedIndex && select.options[index] && !select.options[index].disabled) {
                    select.selectedIndex = index;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    refresh();
                }
            }
        });

        select.addEventListener('change', refresh);
        select.addEventListener('tf-select-refresh', refresh);

        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        wrapper.appendChild(button);
        wrapper.appendChild(menu);
        select.classList.add('tf-native-select');
        refresh();
    };

    const init = (root = document) => root.querySelectorAll(SELECTOR).forEach(build);

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.tf-select')) closeAll();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAll();
    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
    else init();

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (!(node instanceof Element)) continue;
                if (node.matches?.(SELECTOR)) build(node);
                init(node);
            }
        }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
