@extends('layouts.admin', ['title' => 'Monitoring · ' . $schedule->exam->name])
@section('content')
<div x-data="liveMonitor(@js([
        'url' => route('api.monitor.show', $schedule),
        'actionUrl' => route('api.monitor.action', ['attempt' => '__ID__', 'action' => '__ACTION__']),
        'scheduleActionUrl' => route('api.monitor.schedule-action', ['schedule' => $schedule, 'action' => '__ACTION__']),
        'detailUrl' => route('api.monitor.detail', ['attempt' => '__ID__']),
        'csrf' => csrf_token(),
        'pollSec' => (int) config('kitaq.realtime.monitor_poll_sec', 4),
    ]))">
    <x-ui.page-header :title="'Monitoring · ' . $schedule->exam->name" :subtitle="($schedule->classroom?->name ?? 'Peserta khusus') . ' · ' . $schedule->opens_at->format('H:i') . '–' . $schedule->closes_at->format('H:i')" :crumbs="['Monitoring' => route('admin.monitoring.index'), $schedule->exam->name]">
        <x-slot:actions>
            <span class="status" :class="schedule.status === 'open' ? 'st-active' : (schedule.status === 'paused' ? 'st-warned' : 'st-idle')" x-text="schedule.status_label || '…'"></span>
            <span class="muted text-sm" x-show="error" x-cloak style="color:var(--accent)">Koneksi ke server terputus…</span>
            <button type="button" class="btn btn-secondary" x-on:click="toggleMute()" :aria-label="muted ? 'Nyalakan alarm' : 'Bisukan alarm'">
                <svg class="ico" x-show="!muted"><use href="#i-volume"/></svg><svg class="ico" x-show="muted" x-cloak><use href="#i-volumeoff"/></svg>
                <span x-text="muted ? 'Alarm mati' : 'Alarm'"></span>
            </button>
            <x-ui.dropdown label="Aksi massal">
                <x-slot:trigger><button type="button" class="btn btn-secondary">Aksi sesi ▾</button></x-slot:trigger>
                <button type="button" x-show="schedule.status === 'scheduled'" x-on:click="bulk = { action: 'open', label: 'Buka jadwal sekarang?' }"><x-ui.icon name="play" />Mulai sekarang</button>
                <button type="button" x-show="schedule.status === 'open'" x-on:click="bulk = { action: 'pause', label: 'Jeda semua peserta?' }"><x-ui.icon name="pause" />Jeda semua</button>
                <button type="button" x-show="schedule.status === 'paused'" x-on:click="bulk = { action: 'resume', label: 'Lanjutkan ujian semua peserta?' }"><x-ui.icon name="play" />Lanjutkan</button>
                <button type="button" x-on:click="bulk = { action: 'extend', label: 'Perpanjang jadwal & semua peserta aktif?' }"><x-ui.icon name="clock" />Perpanjang semua</button>
                <hr><button type="button" class="danger" x-on:click="bulk = { action: 'close', label: 'Tutup paksa? Semua peserta yang masih mengerjakan dikumpulkan sekarang.' }"><x-ui.icon name="x" />Tutup paksa</button>
            </x-ui.dropdown>
            <x-ui.button variant="secondary" icon="file" :href="route('admin.minutes.show', $schedule)">Berita acara</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mon">
        <div>
            {{-- Ringkasan status (chip = filter) --}}
            <div class="sumchips">
                <template x-for="[key, label, color] in [['all','Semua','var(--info)'],['in_progress','Mengerjakan','var(--success)'],['warned','Peringatan','var(--warning)'],['locked','Terkunci','var(--accent)'],['disconnected','Terputus','#475569'],['not_started','Belum masuk','#64748b'],['submitted','Selesai','var(--info)']]" :key="key">
                    <button type="button" class="sumchip" :class="filter === key && 'on'" :style="'--c:' + color" x-on:click="filter = key">
                        <b class="tabular" x-text="summary[key] ?? 0"></b><small x-text="label"></small>
                    </button>
                </template>
                <div class="input-ico" style="flex:1;min-width:180px"><svg class="ico"><use href="#i-search"/></svg><input class="input" placeholder="Cari nama / NISN…" x-model="search" aria-label="Cari siswa"></div>
            </div>

            <div class="g-students">
                <template x-for="c in visible" :key="c.id">
                    <div class="sc" :class="[statusClass(c.status), c.status === 'locked' && 'locked']" style="--c:var(--c)">
                        <div class="top">
                            <template x-if="c.photo"><img :src="c.photo" alt="" class="avatar" style="object-fit:cover"></template>
                            <template x-if="!c.photo"><span class="avatar" x-text="c.initials"></span></template>
                            <div class="min-w-0"><b class="truncate block" x-text="c.name"></b><small class="tabular" x-text="c.nisn + (c.class ? ' · ' + c.class : '')"></small></div>
                        </div>
                        <div class="row"><span class="status" :class="statusClass(c.status)"><svg class="ico sm"><use :href="'#i-' + statusIcon(c.status)"/></svg><span x-text="statusLabel(c.status) + (c.status === 'warned' ? ' ' + c.warnings + '/' + c.max_warnings : '')"></span></span>
                            <b class="tabular" x-text="c.status === 'submitted' ? (c.score !== null ? 'Nilai ' + Math.round(c.score) : 'Selesai') : mmss(c.remaining)"></b></div>
                        <div class="prog"><div class="progress"><i :style="'width:' + (c.total ? c.answered / c.total * 100 : 0) + '%'"></i></div></div>
                        <div class="row"><span><span x-text="c.answered"></span>/<span x-text="c.total"></span> soal</span><span><span x-text="c.warnings"></span> pelanggaran</span></div>
                        <div class="row" x-show="c.device" style="margin-top:6px"><span class="truncate" x-text="c.device"></span></div>
                        <div class="row" x-show="c.ip" style="margin-top:2px"><span class="tabular" x-text="c.ip"></span><span x-text="c.last_seen !== null ? c.last_seen + ' dtk lalu' : ''"></span></div>
                        <p class="text-xs mt-2" style="color:var(--accent)" x-show="c.lock_reason" x-text="c.lock_reason"></p>
                        <div class="foot" x-show="c.attempt_id">
                            <button type="button" class="btn btn-danger sm" x-show="c.status === 'locked'" x-on:click="open('unlock', c)"><svg class="ico sm"><use href="#i-unlock"/></svg>Buka</button>
                            <button type="button" class="btn btn-secondary sm" x-on:click="showDetail(c)">Log</button>
                            <div class="dropdown" x-data="{ o: false }" x-on:click.outside="o = false">
                                <button type="button" class="btn btn-secondary sm" x-on:click="o = !o" aria-label="Aksi lain">⋯</button>
                                <div class="dropdown-menu" x-show="o" x-cloak x-on:click="o = false">
                                    <button type="button" x-on:click="open('message', c)"><svg class="ico"><use href="#i-message"/></svg>Kirim pesan</button>
                                    <button type="button" x-on:click="open('extend', c)" x-show="c.status !== 'submitted'"><svg class="ico"><use href="#i-clock"/></svg>Tambah waktu</button>
                                    <button type="button" x-on:click="open('reset-login', c)"><svg class="ico"><use href="#i-refresh"/></svg>Reset login</button>
                                    <button type="button" class="danger" x-on:click="open('force-submit', c)" x-show="c.status !== 'submitted'"><svg class="ico"><use href="#i-check"/></svg>Kumpulkan paksa</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div x-show="!cards.length && lastOk" x-cloak><x-ui.empty-state icon="users" title="Belum ada peserta" text="Kelas ini belum punya siswa aktif." /></div>
            <div x-show="!lastOk && !error"><x-ui.skeleton :rows="6" /></div>
        </div>

        <div class="space-y-4">
            <x-ui.card title="Token" :padding="true">
                <div class="token tabular" style="font-size:38px" x-text="token ? token.token.replace(/(.{3})(?=.)/g, '$1 ') : '…'"></div>
                <p class="muted text-sm" x-show="token" x-text="token ? 'Berganti dalam ' + mmss(token.left) + (token.required ? '' : ' · tidak diwajibkan') : ''"></p>
                <div class="flex gap-2 mt-3"><x-ui.button size="sm" icon="monitor" :href="route('admin.projector', $schedule)" target="_blank">Proyektor</x-ui.button><x-ui.button size="sm" variant="secondary" :href="route('admin.sessions', ['schedule' => $schedule->id])">Sesi & Token</x-ui.button></div>
            </x-ui.card>
            <x-ui.card :padding="false">
                <div class="card-head"><div><h2>Feed pelanggaran</h2><p>Diperbarui tiap {{ config('kitaq.realtime.monitor_poll_sec') }} detik</p></div></div>
                <div class="feed mt-3" aria-live="polite">
                    <template x-for="e in feed" :key="e.id">
                        <div class="feed-item">
                            <div class="ic" :class="e.action === 'lock' ? 'tone-danger' : (e.action === 'warn' ? 'tone-warning' : 'tone-neutral')"><svg class="ico sm"><use :href="e.action === 'lock' ? '#i-lock' : (e.action === 'warn' ? '#i-warn' : '#i-info')"/></svg></div>
                            <div class="min-w-0"><b x-text="e.name"></b><small x-text="e.label + (e.action === 'lock' ? ' · terkunci' : (e.action === 'warn' ? ' · peringatan' : ' · dicatat')) + (e.merged ? ' (digabung)' : '')"></small></div>
                            <time x-text="e.time"></time>
                        </div>
                    </template>
                    <p class="muted text-sm p-4" x-show="!feed.length">Belum ada pelanggaran.</p>
                </div>
            </x-ui.card>
        </div>
    </div>

    {{-- Modal aksi per siswa --}}
    <div class="overlay" :class="act && 'show'" x-show="act" x-cloak x-on:click.self="act = null" x-on:keydown.escape.window="act = null" role="dialog" aria-modal="true">
        <div class="modal" x-trap="act">
            <template x-if="act">
                <div>
                    <div class="modal-head"><h2 x-text="({ unlock: 'Buka kunci', 'reset-login': 'Reset login', extend: 'Tambah waktu', 'force-submit': 'Kumpulkan paksa', message: 'Kirim pesan' })[act.type] + ' — ' + act.card.name"></h2><button type="button" class="icon-btn" aria-label="Tutup" x-on:click="act = null"><svg class="ico"><use href="#i-x"/></svg></button></div>
                    <div class="modal-body space-y-3">
                        <template x-if="act.type === 'unlock'"><div class="space-y-3">
                            <p class="text-sm" style="color:var(--accent)" x-text="act.card.lock_reason"></p>
                            <div class="field"><label for="m-reason">Alasan (wajib, tercatat di log)</label><textarea id="m-reason" class="textarea" x-model="reason" placeholder="Mis. sudah diperiksa, notifikasi sistem muncul tanpa sengaja"></textarea></div>
                            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="resetWarnings"> Reset hitungan peringatan ke 0</label>
                            <p class="muted text-xs">Periksa HP siswa dulu: tutup aplikasi lain, matikan ekstensi/terjemahan.</p>
                        </div></template>
                        <template x-if="act.type === 'reset-login'"><p class="text-sm">Sesi & perangkat siswa saat ini dilepas. Siswa login lagi di perangkat baru lalu memasukkan <b>token</b>. Jawaban tetap aman.</p></template>
                        <template x-if="act.type === 'extend'"><div class="field"><label for="m-min">Tambahan waktu (menit)</label><input id="m-min" type="number" min="1" max="240" class="input" x-model.number="minutes"></div></template>
                        <template x-if="act.type === 'force-submit'"><p class="text-sm">Jawaban siswa dikumpulkan sekarang dan dinilai. Tindakan ini tidak bisa dibatalkan.</p></template>
                        <template x-if="act.type === 'message'"><div class="field"><label for="m-msg">Pesan (muncul sebagai notifikasi di HP siswa)</label><input id="m-msg" class="input" maxlength="200" x-model="message" placeholder="Mis. Fokus ke soal, waktu tinggal 10 menit"></div></template>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn btn-secondary" x-on:click="act = null">Batal</button>
                        <button type="button" class="btn" :class="['force-submit', 'unlock'].includes(act.type) ? 'btn-danger' : 'btn-primary'" :disabled="busy" x-on:click="submitAction()"><span class="spin" x-show="busy"></span>Lanjutkan</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Konfirmasi aksi massal --}}
    <div class="overlay" :class="bulk && 'show'" x-show="bulk" x-cloak x-on:click.self="bulk = null" role="dialog" aria-modal="true">
        <div class="modal"><div class="modal-head"><h2>Konfirmasi</h2></div>
            <div class="modal-body"><p x-text="bulk && bulk.label"></p>
                <div class="field mt-3" x-show="bulk && bulk.action === 'extend'"><label for="b-min">Menit</label><input id="b-min" type="number" class="input" min="1" max="240" x-model.number="bulkMinutes"></div></div>
            <div class="modal-foot"><button type="button" class="btn btn-secondary" x-on:click="bulk = null">Batal</button><button type="button" class="btn btn-primary" :class="bulk && bulk.action === 'close' && 'btn-danger'" x-on:click="scheduleAction(bulk.action)" :disabled="busy">Ya, lanjutkan</button></div>
        </div>
    </div>

    {{-- Drawer linimasa perangkat & log --}}
    <div x-show="detail" x-cloak x-on:keydown.escape.window="detail = null">
        <div class="drawer-wrap" x-on:click="detail = null"></div>
        <aside class="drawer" role="dialog" aria-modal="true">
            <div class="drawer-head"><h2 x-text="detail ? detail.card.name : ''"></h2><button type="button" class="icon-btn" aria-label="Tutup" x-on:click="detail = null"><svg class="ico"><use href="#i-x"/></svg></button></div>
            <div class="drawer-body" x-show="detail">
                <p class="muted text-sm mb-3" x-text="detail && detail.started_at ? 'Mulai ' + detail.started_at + ' · batas ' + detail.deadline_at : 'Belum mulai'"></p>
                <b class="text-sm">Perangkat</b>
                <ul class="timeline mb-4"><template x-for="i in (detail?.instances || [])" :key="i.id"><li><time x-text="i.first"></time><span x-text="i.device + ' · ' + (i.ip || '-') + (i.active ? ' · aktif' : '')"></span></li></template></ul>
                <b class="text-sm" x-show="detail?.unlocks?.length">Buka kunci</b>
                <ul class="timeline mb-4"><template x-for="u in (detail?.unlocks || [])"><li><time x-text="u.time"></time><span x-text="u.by + ': ' + u.reason"></span></li></template></ul>
                <b class="text-sm">Linimasa</b>
                <ul class="timeline"><template x-for="(e, i) in (detail?.events || [])" :key="i"><li><time x-text="e.time"></time><span :style="e.action === 'lock' ? 'color:var(--accent)' : (e.action === 'warn' ? 'color:#9a6700' : '')" x-text="e.label + ' · ' + e.action + (e.merged ? ' (gabung)' : '')"></span></li></template></ul>
                <div x-show="detail?.loading"><x-ui.skeleton :rows="5" /></div>
            </div>
        </aside>
    </div>
</div>
@endsection
