const CACHE_NAME = 'securevault-v2';
const SHELL_ASSETS = [
    './',
    './index.php',
    './dashboard.php',
    './js/dashboard.js',
    './js/pwa.js',
    './logo.svg'
];

// Install: cache the app shell
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch: network-first for API/PHP, cache-first for assets
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Always bypass Service Worker for API calls entirely
    if (url.pathname.includes('/api/')) {
        return;
    }

    // Network-first for PHP pages
    if (url.pathname.endsWith('.php')) {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(event.request))
        );
        return;
    }

    // Cache-first for static assets (js, css, images)
    event.respondWith(
        caches.match(event.request).then(cached => cached || fetch(event.request))
    );
});
