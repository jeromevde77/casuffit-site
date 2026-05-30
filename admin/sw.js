// admin/sw.js — Service Worker ça suffit ! Admin PWA
// v4 — 2026-05-30 (dashboard hors precache + skip query strings)
const CACHE = 'csa-admin-v4';

// Ressources à mettre en cache au premier chargement
// (dashboard.php retiré : doit toujours être frais pour les hooks ?action=)
const PRECACHE = [
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
  // Ne JAMAIS intercepter les URLs avec paramètres (?action=, ?edit=, etc.)
  // → toujours frais depuis le serveur, indispensable pour les hooks admin
  if (url.search) return;

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
