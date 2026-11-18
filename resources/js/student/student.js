/**
 * Halaman siswa non-ujian (login, dashboard, token, hasil, panduan) + login staf.
 * Vanilla JS, tanpa Livewire. Halaman token memakai Keyboard KITAQ mode token
 * (huruf & angka tanpa I/O/0/1) sehingga keyboard HP tidak muncul.
 */
import '../../css/student.css';
import { KitaqKeyboard, KitaqTextbox } from '../keyboard/kitaq-keyboard.js';
import { detectPlatform } from '../shield/platform.js';

const $ = (s, r = document) => r.querySelector(s);

// Tema mengikuti sistem / pilihan tersimpan.
try {
  const t = localStorage.getItem('kq:theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.dataset.theme = t;
} catch (e) { /* abaikan */ }

// Service worker PWA (hanya HTTPS / localhost).
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

// Tombol lihat/sembunyikan password.
document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.togglePassword);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.setAttribute('aria-label', input.type === 'password' ? 'Tampilkan' : 'Sembunyikan');
  });
});

// Cegah kirim ganda.
document.querySelectorAll('form[data-once]').forEach((f) => f.addEventListener('submit', () => {
  const b = f.querySelector('[type=submit]'); if (b) { b.disabled = true; b.dataset.label = b.textContent; b.textContent = 'Memproses…'; }
}));

// Banner "Pasang ke Layar Utama" untuk iPhone yang belum mode aplikasi.
const p = detectPlatform();
document.querySelectorAll('[data-ios-install]').forEach((el) => { el.hidden = !(p.os === 'ios' && !p.standalone); });
document.querySelectorAll('[data-platform-os]').forEach((el) => { el.textContent = p.os; });

// Tombol salin tautan (halaman "Buka di browser").
document.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
  const text = btn.dataset.copy;
  try { await navigator.clipboard.writeText(text); btn.textContent = 'Tautan disalin ✓'; }
  catch (e) {
    const box = document.getElementById(btn.dataset.fallback || '');
    if (box) { box.hidden = false; }
  }
}));

/* ---------- Papan token KITAQ ---------- */
const tokenBox = $('#kq-token-box');
if (tokenBox) {
  const hidden = $('#kq-token-value');
  const submit = $('#kq-token-submit');
  const kb = new KitaqKeyboard($('#kq-token-kb'));
  const tb = new KitaqTextbox(tokenBox, {
    value: hidden.value || '',
    mode: 'token',
    maxLength: Number(tokenBox.dataset.length || 6),
    placeholder: 'Ketuk untuk isi token',
    onActivate: (t) => kb.attach(t),
    onChange: (v) => { hidden.value = v; submit.disabled = v.length < Number(tokenBox.dataset.length || 6); },
  });
  submit.disabled = (hidden.value || '').length < Number(tokenBox.dataset.length || 6);
  tb.activate();
  // Laptop: keyboard fisik boleh dipakai untuk mengetik token.
  document.addEventListener('keydown', (e) => {
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key === 'Enter' && !submit.disabled) { submit.closest('form').requestSubmit(); return; }
    if (!tb.active) tb.activate();
    if (e.key === 'Backspace') { tb.backspace(); e.preventDefault(); return; }
    if (e.key && e.key.length === 1) { tb.insert(e.key.toUpperCase()); e.preventDefault(); }
  });
}

/* ---------- Hitung mundur sederhana (dashboard: "dimulai dalam") ---------- */
document.querySelectorAll('[data-countdown]').forEach((el) => {
  let left = Number(el.dataset.countdown);
  const tick = () => {
    if (left <= 0) { if (el.dataset.reload !== undefined) location.reload(); return; }
    left--; const h = Math.floor(left / 3600); const m = Math.floor((left % 3600) / 60); const s = left % 60;
    el.textContent = (h ? h + ':' : '') + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    setTimeout(tick, 1000);
  };
  tick();
});
