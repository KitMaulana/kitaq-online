/**
 * Command palette (Ctrl+K): halaman, aksi, dan pencarian siswa/ujian di server.
 * Item halaman dikirim layout (sesuai role) lewat data-items.
 */
export function registerPalette(Alpine) {
  Alpine.data('commandPalette', (items = [], searchUrl = '') => ({
    open: false,
    q: '',
    idx: 0,
    remote: [],
    timer: null,
    items,
    init() {
      window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.show(); }
        if (e.key === 'Escape' && this.open) this.open = false;
      });
      window.addEventListener('kq:palette', () => this.show());
    },
    show() { this.open = true; this.q = ''; this.idx = 0; this.remote = []; this.$nextTick(() => this.$refs.q && this.$refs.q.focus()); },
    get results() {
      const s = this.q.trim().toLowerCase();
      const local = s ? this.items.filter((i) => (i.label + ' ' + (i.group || '')).toLowerCase().includes(s)) : this.items;
      return [...local.slice(0, 12), ...this.remote];
    },
    search() {
      clearTimeout(this.timer);
      this.idx = 0;
      if (!searchUrl || this.q.trim().length < 2) { this.remote = []; return; }
      this.timer = setTimeout(async () => {
        try {
          const r = await fetch(searchUrl + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' } });
          if (r.ok) this.remote = (await r.json()).items || [];
        } catch (e) { this.remote = []; }
      }, 300);
    },
    move(d) { const n = this.results.length; if (n) this.idx = (this.idx + d + n) % n; },
    go(item) { item = item || this.results[this.idx]; if (item && item.url) window.location.href = item.url; },
  }));
}
