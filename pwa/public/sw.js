const CACHE_NAME = 'baqqala-pwa-v2';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  // Do not intercept or cache API requests or backend requests
  if (event.request.url.includes('/api/') || event.request.url.includes('baqqala-admin')) {
    return;
  }

  // Network-first strategy for assets and HTML
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});
