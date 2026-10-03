// Library pages contain live, signed-in data and must never come from a cache.
self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names.filter((name) => name.startsWith('streamy-'))
      .map((name) => caches.delete(name)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (event.request.method === 'GET' && url.origin === self.location.origin &&
      (event.request.mode === 'navigate' || url.pathname.endsWith('.php'))) {
    event.respondWith(fetch(event.request, { cache: 'no-store' }));
  }
});
