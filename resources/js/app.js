import './bootstrap';

import.meta.glob('../assets/INTSEC.png', { eager: true, query: '?url', import: 'default' });

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) return;
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    });
});

const modalTriggers = document.querySelectorAll('[data-modal-trigger]');
let activeModal = null;
let modalReturnFocus = null;

const modalFocusable = (modal) => [...modal.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
)].filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');

const openModal = (modal, trigger = null) => {
    if (!modal) return;
    activeModal = modal;
    modalReturnFocus = trigger ?? document.activeElement;
    modal.dataset.open = 'true';
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    window.requestAnimationFrame(() => modalFocusable(modal)[0]?.focus());
};

const closeModal = (modal = activeModal) => {
    if (!modal) return;
    modal.dataset.open = 'false';
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    activeModal = null;
    modalReturnFocus?.focus?.();
    modalReturnFocus = null;
};

modalTriggers.forEach((trigger) => {
    trigger.addEventListener('click', () => openModal(document.getElementById(trigger.dataset.modalTrigger), trigger));
});

document.querySelectorAll('[data-modal]').forEach((modal) => {
    modal.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', () => closeModal(modal)));
    modal.addEventListener('mousedown', (event) => {
        if (event.target === modal) closeModal(modal);
    });
    if (modal.dataset.modalAutoOpen === 'true') openModal(modal);
});

document.addEventListener('keydown', (event) => {
    if (!activeModal) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        closeModal();
        return;
    }
    if (event.key !== 'Tab') return;
    const focusable = modalFocusable(activeModal);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

document.querySelectorAll('[data-copy-target]').forEach((button) => {
    button.addEventListener('click', async () => {
        const target = document.getElementById(button.dataset.copyTarget);
        if (!target || !navigator.clipboard) return;
        await navigator.clipboard.writeText(target.innerText);
        const original = button.textContent;
        button.textContent = 'Copied';
        window.setTimeout(() => { button.textContent = original; }, 1800);
    });
});

document.querySelectorAll('[data-select-all]').forEach((control) => {
    control.addEventListener('change', () => {
        document.querySelectorAll(control.dataset.selectAll).forEach((checkbox) => {
            checkbox.checked = control.checked;
        });
    });
});

document.querySelectorAll('form[data-requires-selection]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const fieldName = form.dataset.requiresSelection;
        if (document.querySelectorAll(`input[name="${fieldName}"]:checked`).length) return;
        event.preventDefault();
        window.alert('Select at least one record before applying this action.');
    });
});

const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
if (sidebarToggle) {
    const storageKey = 'intsec.sidebar.collapsed';
    let collapsed = false;
    try { collapsed = window.localStorage.getItem(storageKey) === '1'; } catch (_) { /* storage may be unavailable */ }

    const applySidebarState = (isCollapsed) => {
        document.body.classList.toggle('sidebar-collapsed', isCollapsed);
        sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
        sidebarToggle.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
        sidebarToggle.querySelector('.sr-only').textContent = isCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
        document.querySelectorAll('#intsec-sidebar nav details').forEach((group) => {
            if (isCollapsed) {
                group.dataset.openBeforeCollapse = group.open ? '1' : '0';
                group.open = true;
            } else if (group.dataset.openBeforeCollapse !== undefined) {
                group.open = group.dataset.openBeforeCollapse === '1';
                delete group.dataset.openBeforeCollapse;
            }
        });
    };

    applySidebarState(collapsed);
    sidebarToggle.addEventListener('click', () => {
        collapsed = !collapsed;
        applySidebarState(collapsed);
        try { window.localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (_) { /* non-persistent fallback */ }
    });
}

const realtimeEnabled = document.querySelector('meta[name="intsec-realtime-enabled"]')?.content === '1';

if (realtimeEnabled && window.Echo) {
    const body = document.body;
    const entities = (body.dataset.realtimeEntities ?? '').split(',').map((value) => value.trim()).filter(Boolean);
    const source = body.dataset.realtimeSource ?? '';
    const indicator = document.getElementById('intsec-realtime-indicator');
    const message = document.getElementById('intsec-realtime-message');
    const refresh = document.getElementById('intsec-realtime-refresh');
    let reloadTimer;

    refresh?.addEventListener('click', () => window.location.reload());

    window.Echo.private('intsec.security')
        .error((error) => {
            window.dispatchEvent(new CustomEvent('intsec:realtime-state', { detail: { state: 'auth_failed', error } }));
            if (import.meta.env.DEV) console.error('[INTSEC realtime] Private channel subscription failed', error);
        })
        .listen('.security.state.changed', (event) => {
        window.dispatchEvent(new CustomEvent('intsec:security-state-changed', { detail: event }));

        const entityMatches = entities.includes('*') || entities.includes(event.entity);
        const sourceMatches = source === '' || event.source === null || event.source === source;
        if (!entityMatches || !sourceMatches) return;

        const hasQueryState = window.location.search.length > 0;
        if (!hasQueryState) {
            window.clearTimeout(reloadTimer);
            reloadTimer = window.setTimeout(() => window.location.reload(), 800);
            return;
        }

        if (message) message.textContent = 'New activity is available without changing your current filters.';
        indicator?.classList.remove('hidden');
        });
}
