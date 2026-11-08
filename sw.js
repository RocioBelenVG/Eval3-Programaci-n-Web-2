/**
 * TriTransportes — Service Worker
 * ----------------------------------------------------------------------------
 * Permite que la aplicación siga abriéndose en faena cuando no hay señal.
 *
 * Estrategias:
 *   · Recursos estáticos (CSS/JS/iconos) → primero caché, luego red.
 *   · Páginas (navegación)               → primero red, con respaldo en caché
 *                                          y, en último término, pantalla offline.
 *   · Peticiones POST                    → nunca se interceptan aquí; la cola de
 *                                          envíos pendientes vive en offline.js,
 *                                          donde puede avisar al usuario.
 */

const VERSION      = 'tritransportes-v2';
const CACHE_APP    = `${VERSION}-app`;
const CACHE_PAGINAS = `${VERSION}-paginas`;

// Rutas relativas al scope del service worker (/TriTransportes/)
const RECURSOS_BASE = [
    './',
    './offline.html',
    './assets/css/app.css?v=4',
    './assets/js/app.js?v=4',
    './assets/js/viewer.js?v=4',
    './assets/js/offline.js?v=1',
    './assets/img/icono.svg',
    './manifest.webmanifest',
];

// ── Instalación: se precachea el armazón de la aplicación ───────────────────
self.addEventListener('install', (evento) => {
    evento.waitUntil(
        caches.open(CACHE_APP)
            .then((cache) => cache.addAll(RECURSOS_BASE).catch(() => {
                // Si algún recurso falla, la instalación no debe abortar
                return Promise.resolve();
            }))
            .then(() => self.skipWaiting())
    );
});

// ── Activación: se limpian versiones anteriores ─────────────────────────────
self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(
                claves.filter((c) => !c.startsWith(VERSION)).map((c) => caches.delete(c))
            ))
            .then(() => self.clients.claim())
    );
});

// ── Intercepción de peticiones ──────────────────────────────────────────────
self.addEventListener('fetch', (evento) => {
    const peticion = evento.request;

    // Sólo GET del mismo origen; el resto pasa directo a la red
    if (peticion.method !== 'GET') return;
    if (new URL(peticion.url).origin !== self.location.origin) return;

    // Las exportaciones y la API siempre van a la red: no tiene sentido cachearlas
    if (peticion.url.includes('/api/')) return;

    if (peticion.mode === 'navigate') {
        evento.respondWith(estrategiaPaginas(peticion));
        return;
    }

    if (/\.(css|js|svg|png|jpg|jpeg|webp|woff2?)$/i.test(new URL(peticion.url).pathname)) {
        evento.respondWith(estrategiaEstaticos(peticion));
    }
});

/** Páginas: red primero para tener siempre el dato fresco. */
async function estrategiaPaginas(peticion) {
    try {
        const respuesta = await fetch(peticion);
        // Sólo se guardan páginas completas y correctas
        if (respuesta && respuesta.status === 200 && respuesta.type === 'basic') {
            const cache = await caches.open(CACHE_PAGINAS);
            cache.put(peticion, respuesta.clone());
        }
        return respuesta;
    } catch (e) {
        const enCache = await caches.match(peticion);
        if (enCache) return enCache;

        const offline = await caches.match('./offline.html');
        return offline || new Response(
            '<h1>Sin conexión</h1><p>No fue posible cargar esta página.</p>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
        );
    }
}

/** Estáticos: caché primero, y se actualiza en segundo plano. */
async function estrategiaEstaticos(peticion) {
    const enCache = await caches.match(peticion);
    if (enCache) {
        // Revalidación silenciosa
        fetch(peticion).then((respuesta) => {
            if (respuesta && respuesta.status === 200) {
                caches.open(CACHE_APP).then((c) => c.put(peticion, respuesta));
            }
        }).catch(() => {});
        return enCache;
    }

    try {
        const respuesta = await fetch(peticion);
        if (respuesta && respuesta.status === 200) {
            const cache = await caches.open(CACHE_APP);
            cache.put(peticion, respuesta.clone());
        }
        return respuesta;
    } catch (e) {
        return new Response('', { status: 504 });
    }
}

// Permite que la página fuerce la actualización del service worker
self.addEventListener('message', (evento) => {
    if (evento.data === 'actualizar') self.skipWaiting();
});
