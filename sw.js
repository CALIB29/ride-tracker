const CACHE_NAME = 'ridetracker-v2';
const ASSETS = [
  'index.php',
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png'
];

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(clients.claim());
});

self.addEventListener('fetch', event => {
  // Bypass Service Worker for API calls and PeerJS to ensure real-time data
  if (event.request.url.includes('/api/') || event.request.url.includes('peerjs')) {
    return; // Let the browser handle normally
  }

  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request).then(response => {
        return response || new Response('Offline content not available', {
          status: 503,
          statusText: 'Service Unavailable',
          headers: new Headers({ 'Content-Type': 'text/plain' })
        });
      });
    })
  );
});

