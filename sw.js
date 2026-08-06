// Service Worker for Office File Management CRM PWA
const CACHE_NAME = 'file-crm-v1';
const ASSETS = [
  './',
  './index.php',
  './assets/css/style.css',
  './assets/js/main.js',
  './assets/app_logo_icon.jpg'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS).catch(() => {});
    })
  );
});

self.addEventListener('fetch', (e) => {
  e.respondWith(
    caches.match(e.request).then((response) => {
      return response || fetch(e.request);
    })
  );
});
