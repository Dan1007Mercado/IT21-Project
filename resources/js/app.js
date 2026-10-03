import './bootstrap';

const realtimeEnabled = document.querySelector('meta[name="intsec-realtime-enabled"]')?.content === '1';

if (realtimeEnabled && window.Echo) {
    const body = document.body;
    const entities = (body.dataset.realtimeEntities ?? '').split(',').map((value) => value.trim()).filter(Boolean);
    const source = body.dataset.realtimeSource ?? '';
    const indicator = document.getElementById('intsec-realtime-indicator');
    const message = document.getElementById('intsec-realtime-message');
    const refresh = document.getElementById('intsec-realtime-refresh');
    const status = document.getElementById('intsec-realtime-status');
    let reloadTimer;

    const statusStyles = {
        connected: ['Real-time connected', 'border-emerald-500/40', 'text-emerald-200'],
        connecting: ['Connecting…', 'border-amber-500/40', 'text-amber-200'],
        disconnected: ['Real-time disconnected', 'border-zinc-500/40', 'text-zinc-300'],
        failed: ['Connection failed', 'border-red-500/40', 'text-red-200'],
        auth_failed: ['Private channel auth failed', 'border-red-500/40', 'text-red-200'],
    };

    window.addEventListener('intsec:realtime-state', ({ detail }) => {
        if (!status || !statusStyles[detail.state]) return;
        status.className = `fixed bottom-5 left-5 z-40 rounded-full border bg-zinc-900/95 px-3 py-1.5 text-xs shadow-lg ${statusStyles[detail.state][1]} ${statusStyles[detail.state][2]}`;
        status.textContent = statusStyles[detail.state][0];
    });

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
