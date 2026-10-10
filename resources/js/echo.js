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

    // Autorización de los canales de presencia (POST /broadcasting/auth).
    // Los datos se piden en el momento de suscribirse, no al cargar la página:
    //  - el token CSRF sale del <meta name="csrf-token">;
    //  - window.triviaCredenciales() la define /jugar y devuelve { nombre, token } del
    //    jugador (el host no manda nada: lo autoriza su sesión con PIN).
    channelAuthorization: {
        endpoint: '/broadcasting/auth',
        transport: 'ajax',
        headersProvider: () => ({
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            'Accept': 'application/json',
        }),
        paramsProvider: () => window.triviaCredenciales?.() ?? {},
    },
});
