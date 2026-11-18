/**
 * KITAQ Shield — orkestrator anti-contek KITAQ ONLINE.
 *
 * Prinsip:
 *  1. Klien hanya MENDETEKSI dan MELAPOR. Keputusan peringatan/kunci diambil SERVER
 *     (app/Services/Shield/ViolationPolicy.php). Klien boleh menampilkan penutup lebih dulu
 *     untuk event kelas berat agar terasa cepat, lalu menunggu konfirmasi server.
 *  2. Kunci jawaban tidak pernah dikirim ke HP; soal diambil satu per satu.
 *  3. Semua fitur harus jalan di Android (Chrome/Samsung Internet), iOS/iPadOS (Safari),
 *     HarmonyOS 2–4 dan HarmonyOS NEXT (Huawei Browser/ArkWeb), serta desktop.
 *
 * Pemakaian (lihat resources/views/layouts/exam.blade.php):
 *   import { createShield } from './shield';
 *   const shield = createShield(window.KITAQ_EXAM);
 *   const pre = shield.precheck();          // tampilkan alasan bila !pre.ok
 *   btnMulai.onclick = () => shield.start(); // harus dari ketukan pengguna (fullscreen)
 */
import { detectPlatform, isBrowserAllowed } from './platform.js';
import { EventQueue } from './event-queue.js';
import { Heartbeat } from './heartbeat.js';
import { focusGuard } from './guards/focus-guard.js';
import { windowGuard } from './guards/window-guard.js';
import { tabGuard } from './guards/tab-guard.js';
import { translateGuard } from './guards/translate-guard.js';
import { extensionGuard } from './guards/extension-guard.js';
import { inputGuard } from './guards/input-guard.js';
import { fullscreenGuard } from './guards/fullscreen-guard.js';

/** Event yang langsung memunculkan penutup "menunggu pengawas" di sisi klien. */
const INSTANT_LOCK = new Set(['multi_tab', 'translate_detected', 'ai_extension_detected', 'content_tampered']);

function uid() {
  if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
  return 'i' + Math.random().toString(36).slice(2) + Date.now().toString(36);
}

/**
 * @param {object} cfg  Dari server (window.KITAQ_EXAM):
 *   attemptId, csrf, endpoints:{events, heartbeat}, root (selector), lang,
 *   shield:{ blurGraceMs, splitMinWidthRatio, splitMinHeightRatio, requireFullscreen,
 *            iosRequireStandalone, physicalKeyboard:{mobile,desktop}, browsers:[],
 *            aiSignatures:[], heartbeatSec }
 *   ui: { onState(state), onBlocker(kind, show), onConnection(ok, offlineMs), onCommand(cmd), onDesktopKey(e) }
 */
export function createShield(cfg) {
  const platform = detectPlatform();
  const instanceId = sessionStorage.getItem('kitaq:instance') || uid();
  try { sessionStorage.setItem('kitaq:instance', instanceId); } catch (e) { /* abaikan */ }

  const root = typeof cfg.root === 'string' ? document.querySelector(cfg.root) : cfg.root;
  const ui = cfg.ui || {};
  const config = { lang: cfg.lang || 'id', ...(cfg.shield || {}) };
  let active = false;
  let serverState = { status: 'active' };
  const blockers = new Set();

  const queue = new EventQueue({
    attemptId: cfg.attemptId,
    endpoint: cfg.endpoints.events,
    csrf: cfg.csrf,
    instanceId,
    onServerState: (s) => applyState(s),
  });

  function applyState(s) {
    serverState = s;
    // Saat terkunci, guard tetap terpasang tetapi tidak melapor (isActive=false). Selesai/sesi habis → berhenti.
    if (s.status === 'done' || s.status === 'session_expired') active = false;
    ui.onState && ui.onState(s);
  }

  const ctx = {
    platform, config, root, instanceId,
    isActive: () => active && serverState.status !== 'locked',
    report(type, detail) {
      const ev = queue.push(type, { ...detail, vw: window.innerWidth, vh: window.innerHeight });
      if (INSTANT_LOCK.has(type)) ui.onState && ui.onState({ status: 'locked_pending', reason: type });
      return ev;
    },
    flushBeacon: () => queue.beacon(),
    showBlocker(kind) { blockers.add(kind); ui.onBlocker && ui.onBlocker(kind, true); },
    hideBlocker(kind) { blockers.delete(kind); ui.onBlocker && ui.onBlocker(kind, false); },
    flashBlank() { document.documentElement.classList.add('kq-blank'); setTimeout(() => document.documentElement.classList.remove('kq-blank'), 1500); },
    onDesktopKey: ui.onDesktopKey,
  };

  const guards = {
    focus: focusGuard(ctx),
    window: windowGuard(ctx),
    tab: tabGuard(ctx),
    translate: translateGuard(ctx),
    extension: extensionGuard(ctx),
    input: inputGuard(ctx),
    fullscreen: fullscreenGuard(ctx),
  };

  const heartbeat = new Heartbeat({
    endpoint: cfg.endpoints.heartbeat,
    csrf: cfg.csrf,
    instanceId,
    intervalSec: config.heartbeatSec || 15,
    collect: () => ({
      visible: document.visibilityState === 'visible',
      focused: document.hasFocus(),
      fullscreen: !!(document.fullscreenElement || document.webkitFullscreenElement),
      vw: window.innerWidth, vh: window.innerHeight, sw: screen.width, sh: screen.height,
      online: navigator.onLine,
      blockers: [...blockers],
      pending_events: queue._read().length,
    }),
    onState: applyState,
    onCommand: (c) => ui.onCommand && ui.onCommand(c),
    onConnection: (ok, ms) => {
      ui.onConnection && ui.onConnection(ok, ms);
      if (ok) queue.flush();
    },
  });

  return {
    platform,
    instanceId,

    /** Periksa syarat sebelum tombol Mulai aktif. */
    precheck() {
      const reasons = [];
      if (!isBrowserAllowed(platform, config.browsers)) reasons.push({ code: 'browser_not_allowed', browser: platform.browser });
      const fs = guards.fullscreen.precheck(); if (!fs.ok) reasons.push({ code: fs.reason });
      const w = guards.window.precheck(); if (!w.ok) reasons.push({ code: 'split_screen', detail: w.reason });
      if (!window.fetch || !window.MutationObserver) reasons.push({ code: 'browser_too_old' });
      return { ok: reasons.length === 0, reasons, platform };
    },

    /** Panggil dari handler ketukan tombol "Mulai". */
    async start() {
      await guards.fullscreen.enter();
      Object.values(guards).forEach((g) => g.start());
      active = true;
      queue.push('exam_started', { os: platform.os, browser: platform.browser, device: platform.device, standalone: platform.standalone });
      heartbeat.start();
    },

    /** Daftarkan elemen teks soal setelah merender soal baru (untuk cek integritas/terjemahan). */
    protect(nodes) { guards.translate.protect(nodes); },

    /** Coba kembali ke layar penuh (tombol di penutup 'fullscreen'). */
    async reenterFullscreen() { const ok = await guards.fullscreen.enter(); if (ok) ctx.hideBlocker('fullscreen'); return ok; },

    stop() {
      active = false;
      heartbeat.stop();
      Object.values(guards).forEach((g) => g.stop());
      queue.flush();
    },

    get state() { return serverState; },
  };
}
