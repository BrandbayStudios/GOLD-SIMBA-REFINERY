/* EventHub service worker: makes the site installable and shows an offline page.
   Pages always come from the network (vendor lists, logins and CSRF tokens must be fresh);
   only static files are cached. */
const CACHE = 'eventhub-v1';
const PRECACHE = ['/offline.html', '/icons/icon-192.png', '/icons/icon-512.png', '/css/app.css', '/js/app.js'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Pages: network only, with the offline page as the fallback.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    // CSS, JS and images: serve from the network, refresh the cache, fall back to the cache offline.
    if (/\.(css|js|png|jpg|jpeg|webp|svg|ico|woff2?)$/.test(url.pathname)) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(request))
        );
    }
});
