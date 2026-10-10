// Lingua service worker: offline shell + cached GET data for opened content.
const VERSION = 'lingua-v1';
const SHELL_CACHE = `${VERSION}-shell`;
const DATA_CACHE = `${VERSION}-data`;
const ASSET_CACHE = `${VERSION}-assets`;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.addAll(['/offline'])).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Build assets: cache-first (hashed filenames are immutable).
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.open(ASSET_CACHE).then(async (cache) => {
                const hit = await cache.match(request);
                if (hit) return hit;
                const res = await fetch(request);
                if (res.ok) cache.put(request, res.clone());
                return res;
            })
        );
        return;
    }

    // Read-only learning data: network-first with cache fallback so opened
    // lessons/vocabulary stay readable offline. Auth/session endpoints excluded.
    const cacheableApi = ['/api/levels', '/api/lessons/', '/api/vocabulary', '/api/dashboard', '/api/exercises/'];
    if (url.pathname.startsWith('/api/')) {
        const cacheable = cacheableApi.some((p) => url.pathname.startsWith(p));
        if (!cacheable) return;
        event.respondWith(
            fetch(request)
                .then((res) => {
                    if (res.ok) {
                        const copy = res.clone();
                        caches.open(DATA_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return res;
                })
                .catch(() => caches.match(request).then((hit) => hit || Response.error()))
        );
        return;
    }

    // Navigations: network-first, offline page as fallback.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((res) => {
                    const copy = res.clone();
                    caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));
                    return res;
                })
                .catch(async () => (await caches.match(request)) || (await caches.match('/offline')) || Response.error())
        );
    }
});
