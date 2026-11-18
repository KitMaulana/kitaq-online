# Changelog — KITAQ ONLINE Starter Kit (CiptaKita.id)

## 1.0.0 — 2026-11-18 (aplikasi)
- **Fase 1** Fondasi: Laravel 12 (`php ^8.2`), Tailwind v4 + Inter lokal, tiga entry Vite, dashboard admin custom (sidebar, topbar, tema terang/gelap, Ctrl+K) + 33 komponen `x-ui.*`, spatie/permission (4 role), guard `student`, SecurityHeaders, driver database, Super Admin bawaan, 2FA opsional.
- **Fase 2** Data master (kelas, mapel + guru, ruang, sesi, tahun ajaran), siswa (CRUD, aksi massal, akomodasi, import Excel), pengguna (+ import), login NISN + tanggal lahir (format bebas, rate limit, pembekuan, satu sesi aktif, gerbang browser), kartu login PDF + QR.
- **Fase 3** Bank soal 6 tipe, stimulus, editor TipTap + KaTeX, pratinjau bingkai HP, import Excel + ZIP (anti zip-slip, WebP ≤ 1600 px, sanitasi HTML).
- **Fase 4** Ujian + pengaturan JSON lengkap (profil keketatan & pengecualian), jadwal per kelas, scheduler, Sesi & Token, Layar Proyektor.
- **Fase 5** Ruang ujian (kode referensi diadaptasi), API soal/peta/jawaban/submit tanpa kunci jawaban, PWA, `/pasang`.
- **Fase 6** ShieldService (event idempoten, waktu dijepit, transaksi + lock), heartbeat bernonce, satu instance aktif, auto-submit & deteksi heartbeat hilang, batasan IP & browser.
- **Fase 7** Monitoring live (polling 4 dtk, cache 2 dtk, delta), aksi pengawas, alarm & notifikasi, linimasa perangkat.
- **Fase 8** Rekap nilai, analisis butir, koreksi uraian, rekap pelanggaran, berita acara + daftar hadir, ekspor antrean, IntegrityAnalyzer + halaman tinjauan.
- **Fase 9** Kesehatan sistem, backup terenkripsi + retensi, pembersihan log, `kitaq:seed-load`, rute install darurat, skrip rilis, `docs/RILIS.md`.

## 1.0 — 2026-11-18
- Aplikasi baru **KITAQ ONLINE**, terpisah dari KITAQ offline. Laravel 12 + PHP 8.2, target shared/cloud hosting (polling HTTP, cron, driver database).
- Login siswa **NISN + tanggal lahir (DDMMYYYY)** + token ujian berganti, rate limit, pembekuan akun, satu sesi aktif.
- Dihapus dari versi offline: probe internet berkala, penguncian saat putus jaringan, Mode LAN/Hotspot, pra-cek jaringan, skrip Windows server lokal.
- **KITAQ Shield**: deteksi split screen/jendela mengambang, multi-tab & dua perangkat, terjemahan otomatis (pencegahan + deteksi + hash teks soal), ekstensi/asisten AI, keyboard fisik, field asing, layar penuh & mode aplikasi iPhone, antrean event tahan offline, heartbeat bernonce, Mode Soal Kanvas.
- **Keyboard Virtual KITAQ**: halaman ujian tanpa field teks sehingga keyboard HP (termasuk fitur AI) tidak pernah muncul.
- Server: ViolationPolicy (profil longgar/standar/ketat), StudentCredential, ExamTokenService, UserAgentInspector, SecurityHeaders + 67 test.
- Prototipe admin (Sesi & Token, monitoring jenis pelanggaran baru) dan layar HP (login NISN, Keyboard KITAQ, penutup split screen/terjemahan, panduan iPhone), halaman Cek Perangkat, PWA, uji beban k6, panduan deploy & keamanan data.
