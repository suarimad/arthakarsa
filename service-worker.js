// Ganti versi ini (misal menjadi v2, v3, dst) jika ada update besar pada file CSS/JS/HTML
const CACHE_NAME = 'hris-pwa-v3';
const urlsToCache = [
    '/',
    '/index'
];

// EVENT 1: INSTALL (Menyimpan file statis awal ke cache)
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                return cache.addAll(urlsToCache);
            })
    );
    // Memaksa Service Worker baru untuk langsung aktif tanpa menunggu
    self.skipWaiting(); 
});

// EVENT 2: ACTIVATE (Membersihkan cache versi lama secara otomatis)
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// EVENT 3: FETCH (Strategi Network First, falling back to Cache)
self.addEventListener('fetch', event => {
    // Abaikan request selain GET (seperti POST untuk absen/upload gambar) 
    // karena Service Worker tidak bisa meng-cache request POST.
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        // 1. Coba ambil data terbaru dari Server / Network terlebih dahulu
        fetch(event.request)
            .then(response => {
                // Pastikan response valid sebelum disimpan ke cache
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }

                // Simpan salinan data terbaru ke Cache secara diam-diam (background)
                const responseToCache = response.clone();
                caches.open(CACHE_NAME)
                    .then(cache => {
                        cache.put(event.request, responseToCache);
                    });

                return response; // Tampilkan data terbaru dari server
            })
            .catch(() => {
                // 2. Jika Server mati atau User sedang OFFLINE, barulah ambil dari Cache
                return caches.match(event.request);
            })
    );
});