# Uji Beban (k6) — wajib sebelum ujian massal pertama

Shared hosting punya batas CPU & proses. Ukur kemampuan hosting Anda sendiri.

## Persiapan
1. Pasang k6 di laptop (https://k6.io).
2. Di KITAQ ONLINE buat **ujian uji beban** + siswa dummy (`php artisan kitaq:seed-load 300` — perintah dibuat agent) dengan token tetap (mode uji).
3. Jalankan di luar jam ujian sungguhan.

## Jalankan
```bash
k6 run -e BASE=https://ujian.sekolahanda.sch.id -e VUS=100 -e DURATION=5m tests/load/k6-exam.js
k6 run -e BASE=https://ujian.sekolahanda.sch.id -e VUS=300 -e DURATION=10m tests/load/k6-exam.js
```
Skrip mensimulasikan tiap siswa: login → mulai → ambil soal → simpan jawaban tiap ±20 dtk → heartbeat tiap 15 dtk → event sesekali.

## Target lulus
| Metrik | Target |
|---|---|
| `http_req_failed` | < 1% |
| p95 heartbeat & simpan jawaban | < 800 ms |
| p95 ambil soal | < 1.200 ms |
| CPU/entry process di panel hosting | tidak mentok batas |

Jika gagal: naikkan `KITAQ_HEARTBEAT_SEC` (20–25), bagi sesi bergiliran, aktifkan cache, naik paket (cloud hosting), atau pindah VPS + Reverb.
