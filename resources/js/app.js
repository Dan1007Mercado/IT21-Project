import './bootstrap';

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

    window.Echo.private('intsec.security').listen('.security.state.changed', (event) => {
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
