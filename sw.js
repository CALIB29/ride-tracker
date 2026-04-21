// Service Worker - Deactivated to fix interference
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', () => clients.claim());

// Just let everything pass through to the real server
self.addEventListener('fetch', event => {
  return; 
});
