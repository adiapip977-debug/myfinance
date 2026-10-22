/**
 * MYFINANCE — Service Worker
 * Mengaktifkan instalasi PWA di layar utama perangkat mobile
 */

const CACHE_NAME = 'myfinance-cache-v1';
const STATIC_ASSETS = [
    './assets/css/style.css',
    './assets/js/script.js',
    './manifest.json',
    './assets/icons/icon-192.svg',
    './assets/icons/icon-512.svg'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    // Network-first strategy untuk halaman dinamis PHP, cache-first untuk aset statis
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match('./assets/css/style.css');
            })
        );
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            return cachedResponse || fetch(event.request);
        })
    );
});

