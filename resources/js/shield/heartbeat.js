/**
 * Heartbeat lewat HTTP (polling) — cocok untuk shared hosting tanpa WebSocket.
 * Setiap N detik HP mengirim status singkat; server membalas status ujian otoritatif
 * (aktif/peringatan/terkunci/selesai), sisa waktu menurut jam server, dan perintah
 * dari pengawas (buka kunci, kunci, pesan, tambah waktu, kumpulkan paksa).
 *
 * Jika heartbeat berhenti datang (JS dimatikan/diubah, HP mati, sinyal hilang),
 * server menandai 'heartbeat_gap' sendiri. Status putus koneksi tidak langsung
 * mengunci (internet sekolah/rumah bisa tidak stabil) — lihat config offline_flag_after_sec.
 */
export class Heartbeat {
  constructor({ endpoint, csrf, instanceId, intervalSec = 15, collect, onState, onCommand, onConnection }) {
    Object.assign(this, { endpoint, csrf, instanceId, intervalSec, collect, onState, onCommand, onConnection });
    this.timer = null;
    this.failures = 0;
    this.lastOkAt = Date.now();
    this.nonce = null;
  }

  start() { this.tick(); }
  stop() { clearTimeout(this.timer); }

  async tick() {
    clearTimeout(this.timer);
    const jitter = Math.random() * 1500; // sebar beban agar tidak semua HP datang bersamaan
    try {
      const res = await fetch(this.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
        body: JSON.stringify({ instance_id: this.instanceId, nonce: this.nonce, client_now: Date.now(), ...this.collect() }),
      });
      if (res.status === 401 || res.status === 419) { this.onState({ status: 'session_expired' }); return; }
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      this.nonce = data.nonce || null;
      this.failures = 0;
      this.lastOkAt = Date.now();
      this.onConnection(true, 0);
      if (data.state) this.onState(data.state);
      (data.commands || []).forEach((c) => this.onCommand(c));
      const next = (data.next_in_sec || this.intervalSec) * 1000;
      this.timer = setTimeout(() => this.tick(), next + jitter);
    } catch (e) {
      this.failures++;
      this.onConnection(false, Date.now() - this.lastOkAt);
      const retry = Math.min(3000 * this.failures, this.intervalSec * 1000);
      this.timer = setTimeout(() => this.tick(), retry + jitter);
    }
  }
}
