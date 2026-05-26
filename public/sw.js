const CACHE = 'canteen-pos-v1';
const ASSETS = [
  '/assets/css/styles.css',
  '/assets/icons/icon.svg',
  '/manifest.json',
  '/offline/'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (url.origin !== location.origin) return;

  event.respondWith(
    fetch(request)
      .then((response) => {
        if (request.method === 'GET' && response.ok) {
          const copy = response.clone();
          caches.open(CACHE).then((cache) => cache.put(request, copy));
        }
        return response;
      })
      .catch(() => caches.match(request).then((cached) => cached || fetch(request)))
  );
});
