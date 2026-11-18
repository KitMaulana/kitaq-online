/**
 * Token aktif + cincin hitung mundur (Sesi & Token, Layar Proyektor).
 * Token dihitung di SERVER (ExamTokenService); klien hanya menghitung mundur lalu meminta token baru
 * saat berganti. Tidak ada secret di browser.
 */
export function registerTokenRing(Alpine) {
  Alpine.data('tokenRing', (cfg) => ({
    token: cfg.token,
    left: cfg.left,
    total: cfg.total || 900,
    url: cfg.url,
    timer: null,
    init() {
      this.timer = setInterval(() => this.tick(), 1000);
    },
    destroy() { clearInterval(this.timer); },
    get grouped() { return (this.token || '').replace(/(.{3})(?=.)/g, '$1 '); },
    get mmss() { const m = Math.floor(this.left / 60); const s = this.left % 60; return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`; },
    get offset() { const c = 2 * Math.PI * 58; return c * (1 - this.left / this.total); },
    get circ() { return 2 * Math.PI * 58; },
    async tick() {
      this.left = Math.max(0, this.left - 1);
      if (this.left === 0) await this.refresh();
    },
    async refresh() {
      if (!this.url) return;
      try {
        const r = await fetch(this.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (r.ok) { const d = await r.json(); this.token = d.token; this.left = d.left; this.total = d.total || this.total; }
      } catch (e) { this.left = 5; }
    },
    copy() {
      try { navigator.clipboard.writeText(this.token); window.kqToast && window.kqToast('success', 'Token disalin', this.token); } catch (e) { /* abaikan */ }
    },
  }));
}
