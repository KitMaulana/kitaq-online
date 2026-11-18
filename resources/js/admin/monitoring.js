/**
 * Monitoring Live (pengawas) — polling HTTP tiap 4 detik (tanpa WebSocket, cocok shared hosting).
 * Server men-cache ringkasan 2 detik per jadwal; event dikirim delta (since = id event terakhir).
 * Alarm suara (Web Audio, tanpa berkas), notifikasi browser (izin diminta sekali), judul tab berkedip
 * saat ada siswa terkunci.
 */
const STATUS_ORDER = { locked: 0, warned: 1, disconnected: 2, in_progress: 3, not_started: 4, submitted: 5 };

export function registerMonitoring(Alpine) {
  Alpine.data('liveMonitor', (cfg) => ({
    url: cfg.url,
    actionUrl: cfg.actionUrl,           // .../attempts/__ID__/__ACTION__
    csrf: cfg.csrf,
    interval: (cfg.pollSec || 4) * 1000,
    cards: [],
    feed: [],
    summary: {},
    token: null,
    schedule: {},
    since: 0,
    alertSince: -1,
    filter: 'all',
    search: '',
    muted: localStorage.getItem('kq:mute') === '1',
    lastOk: null,
    error: false,
    timer: null,
    titleTimer: null,
    baseTitle: document.title,
    knownLocked: new Set(),
    first: true,
    // modal aksi
    act: null,          // {type, card}
    reason: '',
    minutes: 10,
    message: '',
    resetWarnings: true,
    busy: false,
    detail: null,       // drawer linimasa

    init() {
      this.poll();
      document.addEventListener('visibilitychange', () => { if (!document.hidden) this.poll(); });
      if ('Notification' in window && Notification.permission === 'default' && !localStorage.getItem('kq:notif-asked')) {
        localStorage.setItem('kq:notif-asked', '1');
        setTimeout(() => Notification.requestPermission().catch(() => {}), 1500);
      }
    },
    destroy() { clearTimeout(this.timer); clearInterval(this.titleTimer); },

    async poll() {
      clearTimeout(this.timer);
      try {
        const r = await fetch(`${this.url}?since=${this.since}&alert_since=${this.alertSince}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (r.status === 401 || r.status === 419) { location.reload(); return; }
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const d = await r.json();
        this.cards = d.cards || [];
        this.summary = d.summary || {};
        this.token = d.token || null;
        this.schedule = d.schedule || {};
        if (d.events && d.events.length) {
          this.feed = [...d.events.slice().reverse(), ...this.feed].slice(0, 150);
          this.since = d.events[d.events.length - 1].id;
        }
        if (d.alerts && d.alerts.length) {
          this.alertSince = d.alerts[d.alerts.length - 1].id;
          if (!this.first) d.alerts.filter((a) => a.kind !== 'init').forEach((a) => this.notify(a));
        }
        this.checkLocked();
        this.lastOk = new Date();
        this.error = false;
        this.first = false;
      } catch (e) {
        this.error = true;
      }
      this.timer = setTimeout(() => this.poll(), document.hidden ? this.interval * 3 : this.interval);
    },

    checkLocked() {
      const locked = this.cards.filter((c) => c.status === 'locked');
      locked.forEach((c) => { if (!this.knownLocked.has(c.id) && !this.first) { this.alarm(); } });
      this.knownLocked = new Set(locked.map((c) => c.id));
      clearInterval(this.titleTimer);
      if (locked.length) {
        let on = false;
        this.titleTimer = setInterval(() => { on = !on; document.title = on ? `🔒 ${locked.length} terkunci — KITAQ` : this.baseTitle; }, 1000);
      } else document.title = this.baseTitle;
    },

    notify(a) {
      const tone = a.kind === 'locked' || a.kind === 'second_instance' ? 'error' : 'warning';
      window.kqToast && window.kqToast(tone, a.title || 'Perhatian', a.message);
      if (tone === 'error') this.alarm();
      if ('Notification' in window && Notification.permission === 'granted' && document.hidden) {
        try { new Notification('KITAQ ONLINE', { body: a.message, icon: '/icons/icon-192.png', tag: 'kq-' + a.id }); } catch (e) { /* abaikan */ }
      }
    },

    alarm() {
      if (this.muted) return;
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        const ctx = this._ctx || (this._ctx = new Ctx());
        [0, 0.28, 0.56].forEach((t) => {
          const o = ctx.createOscillator(); const g = ctx.createGain();
          o.type = 'square'; o.frequency.value = 880;
          g.gain.setValueAtTime(0.0001, ctx.currentTime + t);
          g.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + t + 0.02);
          g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + t + 0.22);
          o.connect(g).connect(ctx.destination); o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.24);
        });
      } catch (e) { /* abaikan */ }
    },
    toggleMute() { this.muted = !this.muted; localStorage.setItem('kq:mute', this.muted ? '1' : '0'); },

    get visible() {
      const s = this.search.trim().toLowerCase();
      return this.cards
        .filter((c) => this.filter === 'all' || c.status === this.filter)
        .filter((c) => !s || c.name.toLowerCase().includes(s) || c.nisn.includes(s))
        .sort((a, b) => (STATUS_ORDER[a.status] - STATUS_ORDER[b.status]) || a.name.localeCompare(b.name));
    },

    open(type, card) { this.act = { type, card }; this.reason = ''; this.message = ''; this.minutes = 10; this.resetWarnings = true; },
    async submitAction() {
      if (!this.act || this.busy) return;
      const { type, card } = this.act;
      if (type === 'unlock' && this.reason.trim().length < 5) { window.kqToast('warning', 'Alasan wajib diisi', 'Tulis alasan membuka kunci (min. 5 karakter).'); return; }
      if (type === 'message' && !this.message.trim()) return;
      this.busy = true;
      try {
        const r = await fetch(this.actionUrl.replace('__ID__', card.attempt_id).replace('__ACTION__', type), {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
          body: JSON.stringify({ reason: this.reason, minutes: this.minutes, message: this.message, reset_warnings: this.resetWarnings }),
        });
        const d = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(d.message || 'Gagal');
        window.kqToast('success', d.message || 'Berhasil');
        this.act = null;
        this.poll();
      } catch (e) {
        window.kqToast('error', 'Aksi gagal', e.message);
      } finally { this.busy = false; }
    },

    bulk: null,         // konfirmasi aksi massal {action, label}
    bulkMinutes: 10,
    async scheduleAction(action) {
      if (this.busy) return;
      this.busy = true;
      try {
        const r = await fetch(cfg.scheduleActionUrl.replace('__ACTION__', action), {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
          body: JSON.stringify({ minutes: this.bulkMinutes }),
        });
        const d = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(d.message || 'Gagal');
        window.kqToast('success', d.message || 'Berhasil');
        this.bulk = null;
        this.poll();
      } catch (e) { window.kqToast('error', 'Aksi gagal', e.message); } finally { this.busy = false; }
    },

    async showDetail(card) {
      this.detail = { card, loading: true, events: [], instances: [], unlocks: [] };
      if (!card.attempt_id) { this.detail.loading = false; return; }
      try {
        const r = await fetch(cfg.detailUrl.replace('__ID__', card.attempt_id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const d = await r.json();
        this.detail = { card, loading: false, ...d };
      } catch (e) { this.detail.loading = false; }
    },

    statusLabel(s) { return ({ not_started: 'Belum masuk', in_progress: 'Mengerjakan', warned: 'Peringatan', locked: 'Terkunci', disconnected: 'Terputus', submitted: 'Selesai' })[s] || s; },
    statusClass(s) { return ({ not_started: 'st-idle', in_progress: 'st-active', warned: 'st-warned', locked: 'st-locked', disconnected: 'st-offline', submitted: 'st-done' })[s] || 'st-idle'; },
    statusIcon(s) { return ({ not_started: 'circle', in_progress: 'pencil', warned: 'warn', locked: 'lock', disconnected: 'wifioff', submitted: 'checkc' })[s] || 'circle'; },
    mmss(sec) { if (sec == null) return '-'; const m = Math.floor(sec / 60); return `${m}:${String(sec % 60).padStart(2, '0')}`; },
  }));
}
