/**
 * Klien ruang ujian KITAQ ONLINE (vanilla JS, tanpa Livewire — ringan untuk HP & shared hosting).
 * Entry Vite: resources/js/exam/exam-app.js  (dimuat hanya di halaman ujian siswa)
 *
 * Elemen yang dibutuhkan ada di resources/views/layouts/exam.blade.php.
 * Data awal dari server: window.KITAQ_EXAM (lihat Blade).
 *
 * Diadaptasi dari kode referensi starter kit (lihat docs/DECISIONS.md): HTML soal tersanitasi + KaTeX,
 * Benar/Salah multi-pernyataan, Menjodohkan dengan ketukan, prefetch 1 soal, overlay jeda/instance ganda,
 * ukuran huruf, audio berbatas putar, dan sinkron jawaban dilanjutkan setelah kunci dibuka.
 */
import '../../css/exam.css';
import { createShield } from '../shield/index.js';
import { KitaqKeyboard, KitaqTextbox } from '../keyboard/kitaq-keyboard.js';
import { AnswerSync } from './answer-sync.js';
import { autoRedraw } from '../shield/canvas-text.js';
import { renderMath } from './math.js';

const C = window.KITAQ_EXAM;
const $ = (s, r = document) => r.querySelector(s);
const TYPE_LABEL = { pg: 'Pilihan ganda', pgk: 'Pilih semua yang benar', bs: 'Benar / Salah', jodoh: 'Menjodohkan', isian: 'Isian singkat', uraian: 'Uraian' };

const REASONS = {
  browser_not_allowed: 'Browser ini tidak diizinkan. Buka KITAQ ONLINE dengan Chrome, Safari, Samsung Internet, atau Huawei Browser.',
  ios_standalone: 'Di iPhone, pasang dulu KITAQ ONLINE ke Layar Utama (Bagikan → Tambah ke Layar Utama), lalu buka dari ikon tersebut.',
  split_screen: 'Tutup layar terpisah / jendela mengambang. Ujian harus memenuhi seluruh layar.',
  browser_too_old: 'Browser terlalu lama. Perbarui browser lalu coba lagi.',
};

const state = { no: C.startNo || 1, total: C.total, q: null, remaining: C.remainingSec, cleanupCanvas: [], cache: new Map(), status: 'active', started: false, font: 0 };

/* ---------- Simpan jawaban ---------- */
const sync = new AnswerSync({
  attemptId: C.attemptId,
  endpoint: C.endpoints.answers,
  csrf: C.csrf,
  onStatus: (s) => {
    const el = $('#kq-save');
    const txt = { saved: 'Tersimpan', saving: 'Menyimpan…', retrying: 'Mencoba lagi…', offline: 'Offline — jawaban aman di HP', locked: 'Ujian terkunci' }[s] || s;
    el.textContent = txt;
    el.dataset.status = s;
  },
});

/** Jawaban lokal yang belum terkirim menang atas jawaban server (offline-first). */
function withLocal(q) {
  const pending = sync._all()[q.id];
  if (pending) { q.answer = pending.answer; q.flagged = !!pending.flagged; }
  return q;
}

/* ---------- Keyboard virtual ---------- */
const keyboard = new KitaqKeyboard($('#kq-kb'));

/* ---------- Shield ---------- */
const shield = createShield({
  attemptId: C.attemptId,
  csrf: C.csrf,
  endpoints: { events: C.endpoints.events, heartbeat: C.endpoints.heartbeat },
  root: '#kq-exam',
  lang: 'id',
  shield: C.shield,
  ui: {
    onState: renderState,
    onBlocker: (kind, show) => { const b = $(`[data-kq-blocker="${kind}"]`); if (b) b.hidden = !show; },
    onConnection: (ok, ms) => {
      const bar = $('#kq-conn');
      bar.hidden = ok;
      if (!ok) bar.textContent = `Koneksi terputus ${Math.round(ms / 1000)} dtk — tetap kerjakan, jawaban disimpan di HP.`;
    },
    onCommand: (c) => {
      if (c.type === 'message') toast(c.text, 8000);
      if (c.type === 'extend') { state.remaining += c.seconds; toast(`Waktu ditambah ${Math.round(c.seconds / 60)} menit`); }
      if (c.type === 'force_submit') submit(true);
      if (c.type === 'reload') location.reload();
    },
    onDesktopKey: (e) => keyboard.handleDesktopKey(e),
  },
});

/* ---------- Prapemeriksaan & mulai ---------- */
function runPrecheck() {
  const pre = shield.precheck();
  const list = $('#kq-precheck-list');
  list.replaceChildren();
  pre.reasons.forEach((r) => { const li = document.createElement('li'); li.textContent = REASONS[r.code] || r.code; list.appendChild(li); });
  $('#kq-precheck').hidden = pre.ok;
  $('#kq-precheck-ok').hidden = !pre.ok;
  $('#kq-start').disabled = !pre.ok;
  return pre;
}
runPrecheck();
// Ulangi pengecekan split screen saat ukuran berubah (siswa menutup layar terpisah).
window.addEventListener('resize', () => { if (!$('#kq-confirm').hidden) runPrecheck(); });

$('#kq-start').addEventListener('click', async () => {
  if (!runPrecheck().ok) return;
  await shield.start();
  state.started = true;
  $('#kq-confirm').hidden = true;
  $('#kq-exam').hidden = false;
  loadQuestion(state.no);
  setInterval(tickTimer, 1000);
  sync.flush();
});

$('[data-kq-blocker="fullscreen"] button')?.addEventListener('click', () => shield.reenterFullscreen());
$('[data-kq-goto-token]')?.addEventListener('click', () => { shield.stop(); location.href = C.endpoints.token; });

/* ---------- Soal ---------- */
async function fetchQuestion(no) {
  if (state.cache.has(no)) return state.cache.get(no);
  const res = await fetch(C.endpoints.question.replace('__NO__', no), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (res.status === 423) { const d = await res.json().catch(() => ({})); renderState(d.state || { status: 'locked' }); return null; }
  if (res.status === 401) { renderState({ status: 'session_expired' }); return null; }
  if (!res.ok) throw new Error('HTTP ' + res.status);
  const q = await res.json();
  state.cache.set(no, q);
  return q;
}

async function loadQuestion(no) {
  try {
    const q = await fetchQuestion(no);
    if (!q) return;
    state.q = withLocal(q); state.no = q.no;
    renderQuestion(state.q);
    window.scrollTo(0, 0);
    // Prefetch 1 soal berikutnya (diizinkan spesifikasi 5.7).
    if (no < state.total && !state.cache.has(no + 1)) fetchQuestion(no + 1).catch(() => {});
  } catch (e) {
    toast('Gagal memuat soal. Periksa koneksi lalu coba lagi.');
  }
}

function setAnswer(q, answer) {
  q.answer = answer;
  sync.set(q.id, q.answer, q.flagged);
}

function renderQuestion(q) {
  state.cleanupCanvas.forEach((f) => f()); state.cleanupCanvas = [];
  keyboard.hide();
  $('#kq-qno').textContent = `Soal ${q.no} dari ${state.total}`;
  $('#kq-qtype').textContent = TYPE_LABEL[q.type] || '';
  $('#kq-progress').style.width = Math.round((q.no / state.total) * 100) + '%';
  const opts = $('#kq-options');
  opts.replaceChildren();
  const protect = [];

  // Mode kanvas: teks digambar (tidak ada teks soal di DOM). Selain itu: HTML tersanitasi server + KaTeX.
  const putText = (el, text, html) => {
    if (C.shield.canvasMode) {
      const cv = document.createElement('canvas'); el.replaceChildren(cv);
      state.cleanupCanvas.push(autoRedraw(cv, text, { size: 17 + state.font * 2 }));
    } else {
      if (html) { el.innerHTML = html; renderMath(el); } else el.textContent = text;
      el.querySelectorAll('img').forEach((img) => { img.alt = img.alt || ''; img.draggable = false; });
      protect.push(el);
    }
  };

  const stim = $('#kq-stimulus');
  stim.hidden = !q.stimulus && !q.stimulus_image;
  $('#kq-stimulus-title').textContent = q.stimulus_title || 'Bacaan';
  if (q.stimulus) putText($('#kq-stimulus-text'), q.stimulus, q.stimulus_html);
  $('#kq-stimulus-img').hidden = !q.stimulus_image;
  if (q.stimulus_image) $('#kq-stimulus-img').src = q.stimulus_image;

  putText($('#kq-qtext'), q.text, q.html);
  $('#kq-qimg').hidden = !q.image;
  if (q.image) $('#kq-qimg').src = q.image;
  renderAudio(q);

  const optButton = (o, selected, role, onTap) => {
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'kq-opt'; b.dataset.key = o.key;
    b.setAttribute('role', role);
    const k = document.createElement('span'); k.className = 'k'; k.textContent = o.label || o.key;
    const t = document.createElement('span'); t.className = 't';
    b.append(k, t);
    putText(t, o.text, o.html);
    if (o.image) { const img = document.createElement('img'); img.src = o.image; img.alt = ''; t.appendChild(img); }
    b.classList.toggle('sel', selected); b.setAttribute('aria-checked', String(selected));
    b.addEventListener('click', onTap);
    return b;
  };

  if (q.type === 'pg' || q.type === 'pgk') {
    const multi = q.type === 'pgk';
    q.options.forEach((o) => {
      const selected = multi ? (q.answer || []).includes(o.key) : q.answer === o.key;
      opts.appendChild(optButton(o, selected, multi ? 'checkbox' : 'radio', () => {
        if (multi) { const set = new Set(q.answer || []); set.has(o.key) ? set.delete(o.key) : set.add(o.key); setAnswer(q, [...set].sort()); }
        else setAnswer(q, q.answer === o.key ? null : o.key);
        renderQuestion(q);
      }));
    });
  } else if (q.type === 'bs') {
    const ans = (q.answer && typeof q.answer === 'object') ? q.answer : {};
    q.options.forEach((o) => {
      const row = document.createElement('div'); row.className = 'kq-bs';
      const t = document.createElement('div'); t.className = 'kq-bs-text';
      const n = document.createElement('b'); n.textContent = o.key + '. ';
      const tt = document.createElement('span'); putText(tt, o.text, o.html);
      t.append(n, tt);
      const btns = document.createElement('div'); btns.className = 'kq-bs-btns';
      [[true, 'Benar'], [false, 'Salah']].forEach(([val, label]) => {
        const b = document.createElement('button'); b.type = 'button'; b.textContent = label;
        b.className = 'kq-chip' + (ans[o.key] === val ? ' sel' : '');
        b.setAttribute('aria-pressed', String(ans[o.key] === val));
        b.addEventListener('click', () => { const a = { ...ans }; if (a[o.key] === val) delete a[o.key]; else a[o.key] = val; setAnswer(q, a); renderQuestion(q); });
        btns.appendChild(b);
      });
      row.append(t, btns);
      opts.appendChild(row);
    });
  } else if (q.type === 'jodoh') {
    const ans = (q.answer && typeof q.answer === 'object') ? q.answer : {};
    const legend = document.createElement('div'); legend.className = 'kq-jodoh-right';
    (q.right || []).forEach((r) => {
      const d = document.createElement('div'); d.className = 'kq-jr';
      const k = document.createElement('b'); k.textContent = r.label + '. ';
      const t = document.createElement('span'); putText(t, r.text, r.html);
      d.append(k, t);
      if (r.image) { const img = document.createElement('img'); img.src = r.image; img.alt = ''; d.appendChild(img); }
      legend.appendChild(d);
    });
    opts.appendChild(legend);
    (q.left || []).forEach((l) => {
      const row = document.createElement('div'); row.className = 'kq-bs';
      const t = document.createElement('div'); t.className = 'kq-bs-text';
      const n = document.createElement('b'); n.textContent = l.key + '. ';
      const tt = document.createElement('span'); putText(tt, l.text, l.html);
      t.append(n, tt);
      const btns = document.createElement('div'); btns.className = 'kq-bs-btns';
      (q.right || []).forEach((r) => {
        const b = document.createElement('button'); b.type = 'button'; b.textContent = r.label;
        b.className = 'kq-chip' + (ans[l.key] === r.key ? ' sel' : '');
        b.setAttribute('aria-label', `Pasangkan ${l.key} dengan ${r.label}`);
        b.addEventListener('click', () => { const a = { ...ans }; if (a[l.key] === r.key) delete a[l.key]; else a[l.key] = r.key; setAnswer(q, a); renderQuestion(q); });
        btns.appendChild(b);
      });
      row.append(t, btns);
      opts.appendChild(row);
    });
  } else if (q.type === 'isian' || q.type === 'uraian') {
    const wrap = document.createElement('div'); wrap.className = 'kq-answer';
    const box = document.createElement('div');
    const count = document.createElement('small'); count.dataset.kqCount = ''; count.className = 'kq-count';
    wrap.append(box, count); opts.appendChild(wrap);
    new KitaqTextbox(box, {
      value: typeof q.answer === 'string' ? q.answer : '',
      multiline: q.type === 'uraian',
      mode: q.numeric ? 'numeric' : 'text',
      maxLength: q.max_length || (q.type === 'uraian' ? 3000 : 120),
      placeholder: q.type === 'uraian' ? 'Ketuk untuk menulis jawaban uraian' : 'Ketuk untuk mengisi jawaban',
      onActivate: (tb) => { keyboard.attach(tb); setTimeout(() => box.scrollIntoView({ block: 'center', behavior: 'smooth' }), 50); },
      onChange: (v) => setAnswer(q, v),
    });
  }
  $('#kq-doubt').classList.toggle('on', !!q.flagged);
  shield.protect(protect);
  $('#kq-prev').disabled = q.no <= 1;
  $('#kq-next').textContent = q.no >= state.total ? 'Peta soal' : 'Berikutnya';
}

/** Audio soal dengan batas putar (dihitung di HP). Elemen <audio> bukan kolom teks → keyboard HP tidak muncul. */
function renderAudio(q) {
  const box = $('#kq-audio');
  box.replaceChildren();
  box.hidden = !q.audio;
  if (!q.audio) return;
  const key = `kitaq:audio:${C.attemptId}:${q.id}`;
  let played = 0; try { played = parseInt(localStorage.getItem(key) || '0', 10); } catch (e) { /* abaikan */ }
  const limit = q.audio_limit || 0;
  const info = document.createElement('small');
  const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'kq-chip';
  const audio = new Audio(q.audio); audio.preload = 'none';
  const update = () => { info.textContent = limit ? ` Diputar ${played}/${limit} kali` : ''; btn.disabled = limit > 0 && played >= limit; btn.textContent = audio.paused ? '▶ Putar audio' : '⏸ Jeda'; };
  btn.addEventListener('click', () => {
    if (!audio.paused) { audio.pause(); update(); return; }
    if (audio.currentTime === 0 || audio.ended) { played++; try { localStorage.setItem(key, String(played)); } catch (e) { /* abaikan */ } }
    audio.play().catch(() => toast('Audio gagal diputar.')); update();
  });
  audio.addEventListener('ended', update); audio.addEventListener('pause', update);
  box.append(btn, info); update();
}

$('#kq-prev').addEventListener('click', () => state.no > 1 && loadQuestion(state.no - 1));
$('#kq-next').addEventListener('click', () => (state.no < state.total ? loadQuestion(state.no + 1) : openMap()));
$('#kq-doubt').addEventListener('click', () => { const q = state.q; if (!q) return; q.flagged = !q.flagged; sync.set(q.id, q.answer ?? null, q.flagged); renderQuestion(q); });
$('#kq-font').addEventListener('click', () => {
  state.font = (state.font + 1) % 3;
  document.body.classList.toggle('kq-font-1', state.font === 1);
  document.body.classList.toggle('kq-font-2', state.font === 2);
  if (state.q) renderQuestion(state.q);
});

async function openMap() {
  keyboard.hide();
  let items = [];
  try {
    const res = await fetch(C.endpoints.map, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    items = (await res.json()).items || [];
  } catch (e) {
    items = Array.from({ length: state.total }, (_, i) => ({ no: i + 1, answered: false, flagged: false }));
  }
  // Gabungkan jawaban lokal yang belum terkirim.
  const pending = sync._all();
  const byNo = {}; state.cache.forEach((q) => { byNo[q.no] = q.id; });
  items.forEach((it) => { const p = pending[byNo[it.no]]; if (p) { it.answered = !isEmpty(p.answer); it.flagged = !!p.flagged; } });
  const grid = $('#kq-map-grid'); grid.replaceChildren();
  items.forEach((it) => {
    const b = document.createElement('button'); b.type = 'button'; b.textContent = it.no;
    b.className = 'kq-qn' + (it.flagged ? ' dbt' : it.answered ? ' ans' : '') + (it.no === state.no ? ' cur' : '');
    b.addEventListener('click', () => { $('#kq-map').hidden = true; loadQuestion(it.no); });
    grid.appendChild(b);
  });
  const answered = items.filter((i) => i.answered).length;
  $('#kq-map-count').textContent = `${answered}/${items.length} dijawab`;
  state.mapStats = { answered, total: items.length, flagged: items.filter((i) => i.flagged).length };
  $('#kq-map').hidden = false;
}
function isEmpty(a) { return a === null || a === undefined || a === '' || (Array.isArray(a) && !a.length) || (typeof a === 'object' && !Array.isArray(a) && !Object.keys(a).length); }

$('#kq-open-map').addEventListener('click', openMap);
$('#kq-map-close').addEventListener('click', () => { $('#kq-map').hidden = true; });
// Jangan pakai confirm()/alert(): dialog bawaan browser bisa memicu blur (pelanggaran palsu).
$('#kq-finish').addEventListener('click', () => {
  $('#kq-map').hidden = true;
  const s = state.mapStats || { answered: 0, total: state.total, flagged: 0 };
  const left = s.total - s.answered;
  const elapsed = Math.round((performance.now() - (state.startedAt || 0)) / 1000);
  const wait = Math.max(0, (C.minSubmitSec || 0) - elapsed);
  $('#kq-submit-info').textContent = wait > 0
    ? `Belum boleh dikumpulkan. Tunggu ${Math.ceil(wait / 60)} menit lagi.`
    : `${left ? left + ' soal belum dijawab. ' : ''}${s.flagged ? s.flagged + ' soal ragu-ragu. ' : ''}Jawaban tidak bisa diubah setelah dikumpulkan.`;
  $('#kq-submit-ok').disabled = wait > 0;
  $('#kq-submit-modal').hidden = false;
});
state.startedAt = performance.now();
$('#kq-submit-cancel').addEventListener('click', () => { $('#kq-submit-modal').hidden = true; });
$('#kq-submit-ok').addEventListener('click', () => submit(false));

async function submit(forced) {
  if (submit.busy) return;
  submit.busy = true;
  $('#kq-submit-ok').disabled = true;
  await sync.flush();
  try {
    const res = await fetch(C.endpoints.submit, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': C.csrf, Accept: 'application/json' } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok && !forced && res.status !== 423) {
      toast(data.message || 'Gagal mengumpulkan. Coba lagi.');
      submit.busy = false; $('#kq-submit-ok').disabled = false; $('#kq-submit-modal').hidden = true;
      return;
    }
    shield.stop();
    location.href = data.redirect || C.endpoints.result;
  } catch (e) {
    toast('Koneksi terputus. Jawaban aman di HP; coba kumpulkan lagi saat online.');
    submit.busy = false; $('#kq-submit-ok').disabled = false;
  }
}

/* ---------- Status dari server ---------- */
function renderState(s) {
  const prev = state.status;
  state.status = s.status;
  const show = (name, on) => { const o = $(`[data-kq-overlay="${name}"]`); if (o) o.hidden = !on; };
  if (typeof s.remaining_sec === 'number') state.remaining = s.remaining_sec;

  if (s.status === 'warned' && s.new_warning) {
    const o = $('[data-kq-overlay="warn"]');
    $('[data-kq-warn-count]', o).textContent = `${s.warnings} dari ${s.max_warnings}`;
    $('[data-kq-warn-reason]', o).textContent = s.reason_text || 'Kamu terdeteksi meninggalkan halaman ujian.';
    const bars = $('[data-kq-warn-bars]', o); bars.replaceChildren();
    for (let i = 1; i <= (s.max_warnings || 2); i++) { const b = document.createElement('i'); if (i <= s.warnings) b.className = 'f'; bars.appendChild(b); }
    o.classList.toggle('last', s.warnings >= s.max_warnings);
    o.hidden = false;
  }
  show('lock', s.status === 'locked' || s.status === 'locked_pending');
  if (s.status === 'locked' || s.status === 'locked_pending') {
    keyboard.hide();
    $('[data-kq-lock-reason]').textContent = s.reason_text || 'Pelanggaran terdeteksi. Hubungi pengawas.';
  }
  show('paused', s.status === 'paused');
  show('blocked', s.status === 'blocked_instance');
  if (s.status === 'blocked_instance' && s.reason_text) $('[data-kq-blocked-reason]').textContent = s.reason_text;

  // Kunci dibuka pengawas → lanjutkan sinkron jawaban yang tertahan.
  if ((prev === 'locked' || prev === 'locked_pending') && (s.status === 'active' || s.status === 'warned')) {
    sync.stopped = false; sync.flush();
    state.cache.clear();
    if (state.started && state.q) loadQuestion(state.no);
    toast('Ujian dibuka kembali oleh pengawas.');
  }
  if (s.status === 'done') { shield.stop(); location.href = C.endpoints.result; }
  if (s.status === 'session_expired') location.href = C.endpoints.login;
}
$('[data-kq-overlay="warn"] button')?.addEventListener('click', (e) => { e.target.closest('[data-kq-overlay]').hidden = true; });

/* ---------- Timer (jam server menjadi patokan; dikoreksi tiap heartbeat) ---------- */
function tickTimer() {
  if (state.status === 'paused') return;
  state.remaining = Math.max(0, state.remaining - 1);
  const h = Math.floor(state.remaining / 3600), m = Math.floor((state.remaining % 3600) / 60), s = state.remaining % 60;
  const el = $('#kq-timer');
  el.textContent = (h ? h + ':' : '') + `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  el.classList.toggle('low', state.remaining < 300);
  if (state.remaining === 0 && state.status !== 'locked') submit(true);
}

function toast(text, ms = 4000) {
  const t = $('#kq-toast'); t.textContent = text; t.hidden = false;
  clearTimeout(toast._t); toast._t = setTimeout(() => { t.hidden = true; }, ms);
}
