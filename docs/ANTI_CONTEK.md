# KITAQ Shield — Anti-Contek KITAQ ONLINE

> Kode: `resources/js/shield/**` (klien), `resources/js/keyboard/**` (Keyboard KITAQ), `app/Services/Shield/**` (server).
> Prinsip: **klien mendeteksi & melapor, server memutuskan.** Semua aturan berlaku sama di Android, iOS/iPadOS, dan HarmonyOS karena hanya memakai API web standar.

---

## 1. Lapisan pertahanan

| # | Lapisan | Berkas | Event yang dilaporkan |
|---|---|---|---|
| 1 | Gerbang browser & prapemeriksaan | `platform.js`, `index.js → precheck()` | (memblokir tombol Mulai) |
| 2 | Fokus & visibilitas | `guards/focus-guard.js` | `tab_hidden`, `window_blur`, `page_leave` (+ info `tab_visible`, `window_focus`) |
| 3 | Jendela: split screen, jendela mengambang, multi-monitor | `guards/window-guard.js` | `split_screen`, `screen_extended` (+ info `split_screen_end`) |
| 4 | Satu tab / satu perangkat | `guards/tab-guard.js` + server (7.2 spesifikasi) | `multi_tab`, `second_instance` |
| 5 | Terjemahan & integritas teks soal | `guards/translate-guard.js` | `translate_detected`, `content_tampered` |
| 6 | Ekstensi / add-on AI | `guards/extension-guard.js` | `ai_extension_detected`, `dom_injection` |
| 7 | Input: keyboard HP, keyboard fisik, salin/tempel, seleksi, pintasan | `guards/input-guard.js` + **Keyboard KITAQ** | `foreign_input_focus`, `ime_input`, `physical_keyboard`, `copy_attempt`, `paste_attempt`, `context_menu`, `shortcut_blocked`, `pointer_left` |
| 8 | Layar penuh / mode aplikasi | `guards/fullscreen-guard.js` | `fullscreen_exit`, `fullscreen_denied` |
| 9 | Event tahan offline | `event-queue.js` | (semua event disimpan & dikirim ulang dengan waktu asli) |
| 10 | Heartbeat & nonce | `heartbeat.js` + server | server: `heartbeat_gap`, `heartbeat_invalid` |
| 11 | Mode Soal Kanvas (opsional) | `canvas-text.js` | — (teks soal tidak ada di DOM) |
| 12 | Watermark, soal satu per satu, acak soal/opsi, kunci di server | Blade + API | — |
| 13 | Analitik setelah ujian | `IntegrityAnalyzer` (dibuat agent) | `integrity_flags` |

---

## 2. Cara kerja fitur yang diminta

### 2.1 Split screen & double window
- **Split screen / jendela mengambang (Android, HarmonyOS), Split View / Slide Over / Stage Manager (iPad):** `window-guard` membandingkan `innerWidth/innerHeight` dengan ukuran layar. Default curiga bila lebar < 80% atau tinggi < 62% layar. Karena keyboard HP tidak pernah muncul di ruang ujian, satu-satunya penyebab viewport mengecil drastis adalah layar dibagi. Rotasi layar dan cubit-zoom diabaikan.
- Saat terdeteksi: penutup "Layar terpisah terdeteksi" langsung menutupi soal (soal tidak terbaca di jendela kecil), event `split_screen` dikirim. Penutup hilang sendiri saat layar kembali penuh.
- Saat siswa **mengetuk aplikasi lain** di split screen, halaman kehilangan fokus → `window_blur` juga tercatat (digabung menjadi satu kejadian oleh server).
- **Double window** di browser yang sama: `tab-guard` (BroadcastChannel + kunci localStorage) → tab kedua diblokir, `multi_tab`.
- **Double window lintas browser/perangkat** (mis. Chrome + Samsung Internet, atau HP + laptop): server melihat dua `instance_id` pada satu attempt → `second_instance` (default kunci).
- Laptop: jendela harus hampir maksimal; monitor tambahan (`screen.isExtended`, Chrome) → `screen_extended`.

### 2.2 Terjemahan otomatis Google Chrome (dan browser lain)
- **Cegah dulu:** `<html lang="id" translate="no" class="notranslate">` + `<meta name="google" content="notranslate">`. Ini membuat Chrome tidak menawarkan terjemahan otomatis.
- **Bila dipaksa** (menu ⋮ → Terjemahkan): Chrome menambah kelas `translated-ltr` ke `<html>` dan menyisipkan `<font>` → `translate_detected`.
- **Safari, Samsung Internet, Huawei Browser, Edge**: penandanya berbeda-beda, jadi KITAQ menyimpan **sidik jari (hash) teks soal** saat dirender dan memeriksanya ulang setiap ada perubahan DOM dan tiap 3 detik. Teks berubah = `translate_detected` (ada tanda terjemahan) atau `content_tampered`.
- Menu seleksi teks ("Terjemahkan", "Telusuri", "Tanya AI") tidak bisa muncul karena seleksi teks dimatikan.

### 2.3 Add-on / ekstensi AI
- Ekstensi pembaca halaman/AI hampir selalu menyuntik elemen (panel samping, tombol mengambang, iframe, style). `extension-guard`:
  1. mencocokkan **tanda tangan ekstensi AI yang dikenal** (`config('kitaq.shield.ai_signatures')`, bisa ditambah admin) → `ai_extension_detected`;
  2. mencari **elemen asing**: elemen baru di `<body>`/`<html>` tanpa atribut `data-kq`, custom element bukan `kq-*`, iframe, style/script tanpa nonce → `dom_injection`;
  3. memeriksa sumber daya `chrome-extension://`, `moz-extension://`, `safari-web-extension://`.
- **Daftar browser yang diizinkan** (default Chrome, Safari, Samsung Internet, Huawei Browser). Edge (Copilot), Opera (Aria), Brave (Leo) punya asisten AI bawaan → diblokir default. Firefox Android mendukung banyak ekstensi → diblokir default. Admin bisa mengubah per ujian.
- **Mode Soal Kanvas**: teks soal digambar di `<canvas>`, sehingga ekstensi "baca halaman" dan terjemahan tidak menemukan teks soal sama sekali.

### 2.4 Keyboard AI di HP → Keyboard Virtual KITAQ
- Keyboard HP (Gboard, SwiftKey, Samsung Keyboard, keyboard iOS, keyboard Huawei) hanya muncul bila ada **field teks** yang difokus. Halaman ujian KITAQ ONLINE **tidak punya field teks sama sekali**:
  - PG/PGK/BS/jodoh dijawab dengan ketukan;
  - isian/uraian memakai `<div role="textbox">` yang isinya diatur JavaScript, huruf dimasukkan dari tombol Keyboard KITAQ.
- Hasilnya keyboard HP **tidak pernah terbuka**, sehingga fitur AI-nya (saran AI, tulis ulang, terjemah di keyboard, dikte, papan klip berisi contekan) tidak bisa dipakai. Ini berlaku sama di Android, iOS, dan HarmonyOS.
- Bila ada field teks asing (mis. disuntik ekstensi) mendapat fokus → langsung dilepas + `foreign_input_focus`. Event `beforeinput` dari luar komponen KITAQ → `ime_input`.
- Keyboard Bluetooth/OTG di HP → `physical_keyboard` (diblokir). Di laptop, keyboard fisik boleh dipakai untuk mengetik jawaban bila ujian mengizinkan.

---

## 3. Profil keketatan (server, `ViolationPolicy::PRESETS`)

| Event | Longgar | **Standar (default)** | Ketat |
|---|---|---|---|
| `tab_hidden`, `page_leave` | peringatan | peringatan | peringatan |
| `window_blur` | catat | peringatan | peringatan |
| `split_screen` | peringatan | peringatan | **kunci** |
| `multi_tab`, `second_instance` | peringatan | **kunci** | **kunci** |
| `fullscreen_exit` | catat | peringatan | peringatan |
| `translate_detected`, `content_tampered` | peringatan | **kunci** | **kunci** |
| `ai_extension_detected` | peringatan | **kunci** | **kunci** |
| `dom_injection` | catat | peringatan | **kunci** |
| `physical_keyboard` | catat | peringatan | peringatan |
| `foreign_input_focus`, `ime_input` | catat | peringatan | **kunci** |
| `screen_extended` | catat | peringatan | **kunci** |
| `shortcut_blocked` | catat | catat | peringatan |
| salin/tempel/klik kanan/`pointer_left` | catat | catat | catat |

- Peringatan ke-(batas + 1) mengunci (default batas 2).
- Event "peringatan" dalam **3 detik** dari kejadian terakhir digabung menjadi satu (satu aksi pindah aplikasi memicu blur + hidden + keluar layar penuh). Jendela tidak diperpanjang, jadi bolak-balik berulang tetap terhitung.
- Pengecualian per ujian bisa menimpa satu event (mis. `{"window_blur":"log"}` untuk siswa dengan HP yang sering memunculkan notifikasi sistem).

---

## 4. Matriks platform

Keterangan: ✅ didukung · ⚠️ didukung dengan catatan · ❌ tidak tersedia di platform tersebut (ada pengganti).

| Fitur | Android (Chrome, Samsung Internet) | iPhone (Safari, mode Layar Utama) | iPad (Safari) | HarmonyOS 2–4 (Huawei Browser/Chrome) | HarmonyOS NEXT / 5+ (Huawei Browser, ArkWeb) | Laptop/PC |
|---|---|---|---|---|---|---|
| Deteksi pindah aplikasi/tab | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Deteksi split screen/jendela mengambang | ✅ | — (iPhone tanpa split screen) | ✅ Split View/Slide Over | ✅ | ✅ | ✅ jendela tidak maksimal / monitor kedua (Chrome) |
| Layar penuh | ✅ | ❌ → wajib buka dari ikon Layar Utama | ✅ | ✅ | ✅ (berbasis Chromium) | ✅ |
| Multi-tab & dua perangkat | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Cegah & deteksi terjemahan | ✅ | ✅ (hash) | ✅ (hash) | ✅ | ✅ (hash) | ✅ |
| Deteksi ekstensi yang menyuntik DOM | ⚠️ Samsung Internet add-on ✅; Chrome Android umumnya tanpa ekstensi | ✅ ekstensi Safari | ✅ | ⚠️ | ⚠️ | ✅ |
| Keyboard KITAQ (keyboard HP tidak muncul) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (+ keyboard fisik opsional) |
| Blok seleksi/menu tekan-lama | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Getar tombol keyboard | ✅ | ❌ (web tidak mendukung) | ❌ | ✅ | ⚠️ | — |
| Pasang sebagai aplikasi (PWA) | ✅ tombol "Pasang" | ✅ manual: Bagikan → Tambah ke Layar Utama | ✅ manual | ✅ | ⚠️ tergantung versi browser | ✅ (Chrome/Edge) |
| Simpan jawaban saat offline | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

Catatan iPhone: halaman `/pasang` dan halaman konfirmasi menampilkan panduan bergambar. Di mode Layar Utama tidak ada bilah alamat, dan pindah ke Safari/aplikasi lain terdeteksi sebagai pindah aplikasi.

---

## 5. Yang tidak bisa dicegah web (jujur)
- Tangkapan/rekam layar dan asisten AI yang membaca layar dari level sistem. (Membuka overlay biasanya membuat halaman kehilangan fokus → tercatat.)
- HP kedua, buku, teman di sebelah.
- Ekstensi yang tidak menyentuh halaman sama sekali.
- Siswa yang memalsukan User-Agent (tertangkap sebagian oleh guard lain).
- Untuk kontrol tingkat sistem → aplikasi native "KITAQ Exam Browser" (Spesifikasi Bagian 16).

---

## 6. Checklist uji manual per platform (wajib sebelum dipakai)

Uji di minimal: 1 HP Android (Chrome **dan** Samsung Internet), 1 iPhone, 1 iPad (jika ada), 1 Huawei HarmonyOS 4, 1 Huawei HarmonyOS NEXT (jika ada), 1 laptop. Isi tabel di `docs/HASIL_UJI_PLATFORM.md`.

| # | Skenario | Hasil yang diharapkan |
|---|---|---|
| 1 | Buka halaman ujian, ketuk soal isian | Keyboard KITAQ muncul, **keyboard HP tidak muncul** |
| 2 | Ketik "Rp 1.500,00" dan uraian 3 baris, geser kursor dengan ketukan | Teks benar, kursor pindah, tersimpan |
| 3 | Pindah ke aplikasi lain lalu kembali | Peringatan 1 dari 2 |
| 4 | Buka split screen dengan aplikasi lain | Penutup muncul ≤ 1 dtk, tercatat |
| 5 | Jendela mengambang/pop-up view (Android/Huawei), Slide Over (iPad) | Sama dengan #4 |
| 6 | Buka URL ujian di tab kedua | Tab kedua diblokir, tercatat `multi_tab` |
| 7 | Login di HP kedua saat ujian berjalan | Ditolak, pengawas menerima notifikasi |
| 8 | Chrome: ⋮ → Terjemahkan | Terkunci (`translate_detected`) |
| 9 | Safari: aA → Terjemahkan (bila muncul) | Terkunci (hash berubah) |
| 10 | Tekan lama teks soal | Tidak ada seleksi/menu |
| 11 | Sambungkan keyboard Bluetooth ke HP lalu mengetik | `physical_keyboard` |
| 12 | Keluar layar penuh (Android/iPad/laptop) | Penutup + tombol kembali, tercatat |
| 13 | iPhone: buka dari Safari biasa (bukan ikon) | Tombol Mulai nonaktif + panduan Layar Utama |
| 14 | Mode pesawat 2 menit saat menjawab, lalu online | Jawaban & event sampai di server; tidak terkunci (default) |
| 15 | Buka dari WhatsApp (webview) | Halaman "buka di browser" |
| 16 | Buka dengan Edge/Opera | Ditolak (default) |
| 17 | Safari iOS dengan ekstensi yang menyuntik panel / Firefox (bila diizinkan sementara) | `ai_extension_detected`/`dom_injection` |
| 18 | Pengawas buka kunci dengan alasan | Siswa lanjut, jawaban utuh |
| 19 | Rotasi layar berkali-kali | **Tidak** ada pelanggaran palsu |
| 20 | Tarik panel notifikasi sebentar (< 1 dtk) | Tidak ada pelanggaran (grace 1,2 dtk) |
