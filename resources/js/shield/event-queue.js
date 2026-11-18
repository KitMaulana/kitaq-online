/**
 * Antrean event pelanggaran yang tahan koneksi putus.
 * - Setiap event diberi nomor urut (seq) per percobaan ujian.
 * - Disimpan di localStorage dulu, lalu dikirim ke server berkelompok.
 * - Jika HP offline (mis. mode pesawat), event TIDAK hilang: dikirim saat online lagi
 *   lengkap dengan waktu kejadian asli. Jadi mematikan internet tidak bisa
 *   "menyembunyikan" pelanggaran.
 */

const MAX_EVENTS = 500;

export class EventQueue {
  constructor({ attemptId, endpoint, csrf, instanceId, onServerState }) {
    this.key = `kitaq:ev:${attemptId}`;
    this.seqKey = `kitaq:seq:${attemptId}`;
    this.endpoint = endpoint;
    this.csrf = csrf;
    this.instanceId = instanceId;
    this.onServerState = onServerState || (() => {});
    this.sending = false;
    this.backoff = 1000;
    window.addEventListener('online', () => this.flush());
  }

  _read() {
    try { return JSON.parse(localStorage.getItem(this.key) || '[]'); } catch (e) { return this._mem || []; }
  }
  _write(list) {
    this._mem = list;
    try { localStorage.setItem(this.key, JSON.stringify(list.slice(-MAX_EVENTS))); } catch (e) { /* storage penuh/privat */ }
  }
  _nextSeq() {
    let n = 0;
    try { n = parseInt(localStorage.getItem(this.seqKey) || '0', 10) + 1; localStorage.setItem(this.seqKey, String(n)); }
    catch (e) { this._seq = (this._seq || 0) + 1; n = this._seq; }
    return n;
  }

  push(type, detail = {}) {
    const ev = { seq: this._nextSeq(), type, at: Date.now(), detail };
    const list = this._read(); list.push(ev); this._write(list);
    this.flush();
    return ev;
  }

  async flush() {
    if (this.sending || !navigator.onLine) return;
    const list = this._read();
    if (!list.length) return;
    this.sending = true;
    try {
      const res = await fetch(this.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
        body: JSON.stringify({ instance_id: this.instanceId, client_now: Date.now(), events: list.slice(0, 50) }),
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      const acked = new Set(data.acked || []);
      this._write(this._read().filter((e) => !acked.has(e.seq)));
      this.backoff = 1000;
      if (data.state) this.onServerState(data.state);
      if (this._read().length) setTimeout(() => this.flush(), 50);
    } catch (e) {
      setTimeout(() => this.flush(), this.backoff);
      this.backoff = Math.min(this.backoff * 2, 30000);
    } finally {
      this.sending = false;
    }
  }

  /** Dipanggil saat halaman ditutup/ditinggal: kirim sisa event dengan sendBeacon. */
  beacon() {
    const list = this._read();
    if (!list.length || !navigator.sendBeacon) return;
    const body = new Blob([JSON.stringify({ _token: this.csrf, instance_id: this.instanceId, client_now: Date.now(), events: list.slice(0, 50), beacon: true })], { type: 'application/json' });
    navigator.sendBeacon(this.endpoint, body);
  }
}
