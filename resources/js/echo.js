import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
        },
    },
});

const connection = window.Echo.connector.pusher.connection;
const emitState = (state, detail = {}) => window.dispatchEvent(new CustomEvent('intsec:realtime-state', {
    detail: { state, ...detail },
}));

connection.bind('connecting', () => emitState('connecting'));
connection.bind('connected', () => emitState('connected'));
connection.bind('disconnected', () => emitState('disconnected'));
connection.bind('unavailable', () => emitState('failed'));
connection.bind('failed', () => emitState('failed'));
connection.bind('error', (error) => {
    emitState('failed', { error });
    if (import.meta.env.DEV) console.error('[INTSEC realtime] WebSocket error', error);
});
