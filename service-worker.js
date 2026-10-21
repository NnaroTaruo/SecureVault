const APP_URL = new URL('./', self.registration.scope);
const CACHE_PREFIX = 'securevault:' + encodeURIComponent(APP_URL.pathname) + ':';
const CACHE_NAME = CACHE_PREFIX + 'v14';
const OFFLINE_URL = new URL('dashboard.php?offline_shell=1', APP_URL).href;
const PUBLIC_ASSETS = ['','index.php','manifest.json','logo.svg','icon-192.png','icon-512.png','css/style.css','js/vault-storage.js?v=1','js/vault-crypto.js?v=1','js/dashboard.js?v=12','js/pwa.js'];
const publicUrls = new Set(PUBLIC_ASSETS.map(path => new URL(path, APP_URL).href));

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);
        // A redirected sign-in response must never become the offline vault page.
        const shell = await fetch(OFFLINE_URL, {cache:'no-store', credentials:'omit'});
        if (!shell.ok || shell.headers.get('X-SecureVault-Offline-Shell') !== '1') throw new Error('Offline shell unavailable');
        await cache.put(OFFLINE_URL, shell);
        await Promise.allSettled([...publicUrls].map(async url => {
            const response = await fetch(url, {cache:'no-store', credentials:'omit'});
            if (response.ok && response.url === url) await cache.put(url, response);
        }));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        for (const name of await caches.keys()) {
            if (name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME) await caches.delete(name);
            // Migrate only this app's entries; leave unrelated applications' caches alone.
            if (/^securevault-v\d+$/.test(name)) {
                const cache = await caches.open(name);
                for (const request of await cache.keys()) {
                    const url = new URL(request.url);
                    if (url.origin === APP_URL.origin && url.pathname.startsWith(APP_URL.pathname)) await cache.delete(request);
                }
                if (!(await cache.keys()).length) await caches.delete(name);
            }
        }
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    const inApp = url.origin === APP_URL.origin && url.pathname.startsWith(APP_URL.pathname);
    const externalAsset = url.protocol === 'https:' && ['cdn.tailwindcss.com','fonts.googleapis.com','fonts.gstatic.com'].includes(url.hostname);
    if (!inApp && !externalAsset) return;
    if (inApp && url.pathname.startsWith(new URL('api/', APP_URL).pathname)) {
        event.respondWith(fetch(event.request).catch(() => new Response(JSON.stringify({success:false,offline:true,message:'Network offline.'}), {status:503,headers:{'Content-Type':'application/json','Cache-Control':'no-store'}})));
        return;
    }
    if (event.request.method !== 'GET') return;
    const privateNavigation = inApp && (event.request.mode === 'navigate' || url.pathname.endsWith('.php')) && !publicUrls.has(url.href);
    if (privateNavigation) {
        // Always fetch personal pages from the server; cache only the deliberately neutral shell.
        event.respondWith(fetch(event.request).catch(async () => {
            const cache = await caches.open(CACHE_NAME);
            const shell = await cache.match(OFFLINE_URL);
            return shell || new Response('Connect to the internet to open SecureVault.', {status:503,headers:{'Content-Type':'text/plain'}});
        }));
        return;
    }
    if (!publicUrls.has(url.href) && !externalAsset) return;
    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME);
        const saved = await cache.match(event.request);
        if (saved) return saved;
        const response = await fetch(event.request);
        if (response.ok || (externalAsset && response.type === 'opaque')) await cache.put(event.request, response.clone());
        return response;
    })());
});