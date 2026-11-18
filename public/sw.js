/* KITAQ ONLINE service worker
 * - Menyimpan "kerangka aplikasi" (CSS/JS/font/logo hasil build) agar halaman tetap tampil saat sinyal lemah.
 * - TIDAK menyimpan soal, jawaban, atau respons API (data ujian selalu dari server, privasi siswa terjaga).
 * - Jawaban saat offline disimpan oleh resources/js/exam/answer-sync.js (localStorage), bukan di sini.
 */
const VERSION = 'kitaq-online-v1';
const SHELL = ['/offline.html', '/images/kitaq-online-logo.png', '/icons/icon-192.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== location.origin) return;
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/ujian/') || url.pathname.startsWith('/admin')) return; // selalu jaringan
  // Aset build Vite berversi (hash) → cache-first
  if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/fonts/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/images/')) {
    e.respondWith(caches.open(VERSION).then(async (c) => {
      const hit = await c.match(e.request);
      if (hit) return hit;
      const res = await fetch(e.request);
      if (res.ok) c.put(e.request, res.clone());
      return res;
    }));
    return;
  }
  // Halaman: jaringan dulu, jatuh ke offline.html
  if (e.request.mode === 'navigate') {
    e.respondWith(fetch(e.request).catch(() => caches.match('/offline.html')));
  }
});
