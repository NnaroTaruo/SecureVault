const CACHE_NAME = 'securevault-v9';
const SHELL_ASSETS = [
    './',
    './index.php',
    './login.php',
    './dashboard.php',
    './manifest.json',
    './logo.svg',
    './icon-192.png',
    './icon-512.png',
    './css/style.css',
    './js/dashboard.js',
    './js/pwa.js'
];

// Install: pre-cache the app shell
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            // Use Promise.allSettled so missing optional files don't break install
            return Promise.allSettled(
                SHELL_ASSETS.map(url =>
                    fetch(url, { cache: 'no-cache' })
                        .then(response => {
                            if (response.ok) return cache.put(url, response);
                        })
                        .catch(err => console.warn('SW pre-cache warning for ' + url, err))
                )
            );
        })
    );
    self.skipWaiting();
});

// Activate: clean old cache versions immediately
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch Strategy
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // API calls: Network-first, return JSON offline indicator on failure
    if (url.pathname.includes('/api/')) {
        event.respondWith(
            fetch(event.request).catch(() => {
                return new Response(
                    JSON.stringify({
                        success: false,
                        offline: true,
                        message: 'Network offline. Using local encrypted vault.'
                    }),
                    { headers: { 'Content-Type': 'application/json' } }
                );
            })
        );
        return;
    }

    // Page Navigations: Network-first, fallback to cached app shell on network failure
    if (event.request.mode === 'navigate' || url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    if (response && response.status === 200) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                    }
                    return response;
                })
                .catch(() => {
                    return caches.match(event.request).then(cached => {
                        return cached || caches.match('./dashboard.php') || caches.match('./index.php');
                    });
                })
        );
        return;
    }

    // Static Assets: Cache-first with background network update
    event.respondWith(
        caches.match(event.request).then(cached => {
            if (cached) {
                // Background update
                fetch(event.request).then(fresh => {
                    if (fresh && fresh.status === 200) {
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, fresh));
                    }
                }).catch(() => {});
                return cached;
            }
            return fetch(event.request).then(response => {
                if (response && response.status === 200) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                }
                return response;
            });
        })
    );
});
