const CACHE_NAME = 'intep-v1';
const OFFLINE_URL = '/intep/login.php';

const PRECACHE = [
    '/intep/login.php',
    '/intep/favicon/android-chrome-192x192.png',
    '/intep/favicon/android-chrome-512x512.png',
    '/intep/favicon/site.webmanifest'
];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll(PRECACHE);
        }).then(function() {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(key) { return key !== CACHE_NAME; })
                    .map(function(key) { return caches.delete(key); })
            );
        }).then(function() {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function(event) {
    if (event.request.method !== 'GET') return;

    event.respondWith(
        fetch(event.request)
            .then(function(response) {
                if (response && response.status === 200) {
                    var responseClone = response.clone();
                    caches.open(CACHE_NAME).then(function(cache) {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(function() {
                return caches.match(event.request)
                    .then(function(cached) {
                        return cached || caches.match(OFFLINE_URL);
                    });
            })
    );
});
