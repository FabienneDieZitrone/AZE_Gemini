// Service Worker für AZE_Gemini PWA
// Issue #67: Service Worker für Offline-Support

const CACHE_NAME = 'aze-gemini-v1';
const urlsToCache = [
  '/',
  '/index.html',
  '/static/css/main.css',
  '/static/js/main.js',
  '/manifest.json',
  '/favicon.ico'
];

// Install Event - Cache wichtige Dateien
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch Event - Network first, fallback to cache
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Clone response für Cache
        if (response && response.status === 200) {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME)
            .then(cache => {
              cache.put(event.request, responseToCache);
            });
        }
        return response;
      })
      .catch(() => {
        // Bei Offline: Aus Cache laden
        return caches.match(event.request);
      })
  );
});

// Activate Event - Alte Caches löschen
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

// Background Sync für Offline-Zeiterfassung
self.addEventListener('sync', event => {
  if (event.tag === 'sync-time-entries') {
    event.waitUntil(syncTimeEntries());
  }
});

async function syncTimeEntries() {
  // Hole gespeicherte Offline-Einträge
  const db = await openDB();
  const entries = await db.getAllFromIndex('time-entries', 'synced', 0);
  
  for (const entry of entries) {
    try {
      await fetch('/api/time-entries.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(entry)
      });
      
      // Markiere als synchronisiert
      entry.synced = 1;
      await db.put('time-entries', entry);
    } catch (error) {
      console.error('Sync failed for entry:', entry);
    }
  }
}