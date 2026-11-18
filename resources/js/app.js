/**
 * KITAQ ONLINE — entry admin (Vite). Livewire 3 + Alpine dibundel lokal (tanpa CDN).
 * Halaman ujian siswa memakai entry terpisah: resources/js/exam/exam-app.js (tanpa Livewire).
 */
import '../css/app.css';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { registerTheme } from './admin/theme.js';
import { registerToast } from './admin/toast.js';
import { registerPalette } from './admin/command-palette.js';
import { registerCharts } from './admin/charts.js';
import { registerEditor } from './admin/editor.js';
import { registerMonitoring } from './admin/monitoring.js';
import { registerTokenRing } from './admin/token-ring.js';

registerTheme(Alpine);
registerToast(Alpine);
registerPalette(Alpine);
registerCharts(Alpine);
registerEditor(Alpine);
registerMonitoring(Alpine);
registerTokenRing(Alpine);

// Sidebar: lipat (desktop) / drawer (HP). Pilihan lipat disimpan per browser.
Alpine.data('appShell', () => ({
  collapsed: localStorage.getItem('kq:sidebar') === '1',
  open: false,
  toggle() {
    if (window.innerWidth <= 900) { this.open = !this.open; return; }
    this.collapsed = !this.collapsed;
    try { localStorage.setItem('kq:sidebar', this.collapsed ? '1' : '0'); } catch (e) { /* abaikan */ }
  },
}));

// Peringatan saat meninggalkan form dengan perubahan belum tersimpan (DESIGN_SYSTEM 5.2).
Alpine.data('dirtyGuard', () => ({
  dirty: false,
  init() {
    this.$el.addEventListener('input', () => { this.dirty = true; });
    this.$el.addEventListener('submit', () => { this.dirty = false; });
    window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
    Livewire.hook('commit', ({ succeed }) => succeed(() => { if (this.$el.dataset.cleanOnSave !== undefined) this.dirty = false; }));
  },
}));

Livewire.start();
