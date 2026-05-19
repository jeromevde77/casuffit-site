// admin/sw.js — Service Worker ça suffit ! Admin PWA
const CACHE = 'csa-admin-v2';

// Ressources à mettre en cache au premier chargement
const PRECACHE = [
  '/admin/dashboard.php',
  '/admin/login.php',
  '/favicon-192.png',
  '/favicon-32.png',
  '/favicon.ico',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  // Supprimer les anciens caches
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  // Stratégie : network-first, fallback cache
  // On ne met en cache que les requêtes GET de l'admin
  if (e.request.method !== 'GET') return;
  const url = new URL(e.request.url);
  if (!url.pathname.startsWith('/admin/')) return;

  e.respondWith(
    fetch(e.request)
      .then(res => {
        // Mettre à jour le cache si réponse OK
        if (res.ok) {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return res;
      })
      .catch(() => caches.match(e.request))
  );
});
