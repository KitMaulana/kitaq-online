# Keamanan & Privasi Data — KITAQ ONLINE

## 1. Data pribadi yang diproses
NISN, nama, tanggal lahir, jenis kelamin, kelas, foto (opsional), IP & jenis perangkat saat ujian, log pelanggaran, jawaban dan nilai. Data ini termasuk **data pribadi**, dan data siswa di bawah umur perlu perlakuan hati-hati menurut **UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi**. Sekolah adalah pengendali data; CiptaKita.id/operator hosting sebagai pemroses. (Ini bukan nasihat hukum; konsultasikan kebijakan sekolah/dinas.)

## 2. Prinsip yang wajib diterapkan agent
| Aspek | Penerapan |
|---|---|
| Minimalkan data | Simpan hanya kolom di Spesifikasi Bagian 6. Tanpa geolokasi GPS, tanpa kamera/mikrofon (diblokir `Permissions-Policy`). |
| Password siswa | Hash bcrypt dari `DDMMYYYY`. Tanggal lahir asli di kolom `birth_date` hanya untuk reset & identitas. |
| Tampilan | Tanggal lahir disamarkan di UI (`20-10-****`). Tidak dicetak di kartu login. |
| Akses | Ekspor data siswa hanya Super Admin & Operator; setiap ekspor dicatat di `activity_logs`. Guru hanya melihat siswa kelas yang diajar. |
| Transport | HTTPS wajib + HSTS; cookie `Secure`, `HttpOnly`, `SameSite=Lax`. |
| Header | `SecurityHeaders` (CSP, X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy). |
| Retensi | Log pelanggaran & heartbeat dihapus otomatis setelah `KITAQ_LOG_RETENTION_DAYS` (default 365). Siswa lulus → arsip/hapus sesuai kebijakan sekolah. |
| Transparansi | Halaman konfirmasi ujian menyebutkan apa yang dipantau (aktivitas di halaman ujian, jenis perangkat, IP) dan untuk apa. |

## 3. Risiko tanggal lahir sebagai password & mitigasinya
Tanggal lahir mudah diketahui teman. Karena itu KITAQ ONLINE menambah:
1. **Token ujian** berganti 15 menit, hanya tampil di ruang ujian (lapisan utama melawan joki dari luar).
2. **Rate limit** 5/menit per NISN+IP dan **pembekuan** 15 menit setelah 10 gagal.
3. **Satu sesi aktif**: login kedua saat ujian ditolak dan pengawas diberi tahu.
4. **Batasan IP sekolah** (opsional per jadwal).
5. Opsi **wajib ganti password** saat login pertama (default mati sesuai keputusan sekolah).
6. Log login (waktu, IP, perangkat) yang bisa dilihat operator.

## 4. Akun staf
- Password minimal 10 karakter, cek kebocoran sederhana (daftar password umum).
- 2FA TOTP opsional (wajib disarankan untuk Super Admin).
- Sesi admin berakhir 2 jam tanpa aktivitas; logout semua perangkat.
- Buka kunci ujian, ubah nilai, reset login, ekspor → selalu tercatat siapa & kapan.

## 5. Server
- `APP_DEBUG=false`, `APP_ENV=production`.
- Kode di luar `public_html`; `.env`, `storage/`, `vendor/` tidak bisa diakses dari web.
- Backup harian terenkripsi (zip ber-password dari `APP_KEY` turunan) dan unduh rutin ke penyimpanan sekolah.
- Perbarui dependensi (`composer audit`) sebelum tiap semester. Rencanakan upgrade ke Laravel 13 sebelum 24 Feb 2027.
