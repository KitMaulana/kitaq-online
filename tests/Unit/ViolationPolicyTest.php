<?php

use App\Services\Shield\ShieldState;
use App\Services\Shield\ViolationPolicy;

it('memberi peringatan lalu mengunci pada pelanggaran ketiga (maks 2 peringatan)', function () {
    $p = ViolationPolicy::fromPreset('standar');
    $s = new ShieldState();

    $d1 = $p->evaluate($s, 'tab_hidden', 1000);
    expect($d1->action)->toBe('warn');
    expect($d1->newWarning)->toBeTrue();
    expect($d1->state->warnings)->toBe(1);

    $d2 = $p->evaluate($d1->state, 'window_blur', 1010);
    expect($d2->state->warnings)->toBe(2);
    expect($d2->state->status)->toBe('warned');

    $d3 = $p->evaluate($d2->state, 'tab_hidden', 1020);
    expect($d3->action)->toBe('lock');
    expect($d3->locked)->toBeTrue();
    expect($d3->state->status)->toBe('locked');
});

it('menggabungkan event dari satu aksi dalam jendela 3 detik', function () {
    $p = ViolationPolicy::fromPreset('standar');
    $a = $p->evaluate(new ShieldState(), 'window_blur', 2000);
    $b = $p->evaluate($a->state, 'tab_hidden', 2001);
    $c = $p->evaluate($b->state, 'fullscreen_exit', 2002);
    expect($b->merged)->toBeTrue();
    expect($c->merged)->toBeTrue();
    expect($c->state->warnings)->toBe(1);
});

it('tidak memperpanjang jendela gabung sehingga bolak-balik tetap terhitung', function () {
    $p = ViolationPolicy::fromPreset('standar');
    $s = $p->evaluate(new ShieldState(), 'tab_hidden', 100)->state;
    $s = $p->evaluate($s, 'tab_hidden', 102)->state;  // digabung
    $d = $p->evaluate($s, 'tab_hidden', 104);         // 4 detik dari kejadian pertama → dihitung
    expect($d->merged)->toBeFalse();
    expect($d->state->warnings)->toBe(2);
});

it('langsung mengunci untuk terjemahan, ekstensi AI, dan multi tab pada profil standar', function (string $type) {
    $d = ViolationPolicy::fromPreset('standar')->evaluate(new ShieldState(), $type, 50);
    expect($d->action)->toBe('lock');
    expect($d->state->lockReason)->toBe($type);
})->with(['translate_detected', 'ai_extension_detected', 'multi_tab', 'second_instance', 'content_tampered']);

it('profil ketat mengunci split screen, profil longgar hanya memperingatkan', function () {
    expect(ViolationPolicy::fromPreset('ketat')->actionFor('split_screen'))->toBe('lock');
    expect(ViolationPolicy::fromPreset('longgar')->actionFor('split_screen'))->toBe('warn');
});

it('menerima pengecualian per ujian', function () {
    $p = ViolationPolicy::fromPreset('standar', ['window_blur' => 'log']);
    expect($p->actionFor('window_blur'))->toBe('log');
});

it('mengabaikan event informasi dan mencatat event tak dikenal', function () {
    $p = ViolationPolicy::fromPreset('standar');
    expect($p->actionFor('exam_started'))->toBe('ignore');
    expect($p->actionFor('split_screen_end'))->toBe('ignore');
    expect($p->actionFor('sesuatu_baru'))->toBe('log');
});

it('tidak mengubah status yang sudah terkunci', function () {
    $p = ViolationPolicy::fromPreset('standar');
    $locked = new ShieldState('locked', 3, 10, 'tab_hidden');
    $d = $p->evaluate($locked, 'tab_hidden', 500);
    expect($d->state)->toBe($locked);
    expect($d->action)->toBe('log');
});

it('membuka kunci dan mereset peringatan', function () {
    $p = ViolationPolicy::fromPreset('standar');
    $s = $p->unlock(new ShieldState('locked', 3, 10, 'translate_detected'));
    expect($s->status)->toBe('active');
    expect($s->warnings)->toBe(0);
    expect($s->lockReason)->toBeNull();

    $keep = $p->unlock(new ShieldState('locked', 3, 10, 'tab_hidden'), false);
    expect($keep->status)->toBe('warned');
    expect($keep->warnings)->toBe(2);
});

it('menolak aksi tidak valid dan profil tak dikenal', function () {
    expect(fn () => new ViolationPolicy(['tab_hidden' => 'kick']))->toThrow(InvalidArgumentException::class);
    expect(fn () => ViolationPolicy::fromPreset('super'))->toThrow(InvalidArgumentException::class);
});

it('maksimum peringatan 0 berarti pelanggaran pertama langsung mengunci', function () {
    $p = ViolationPolicy::fromPreset('standar', [], 0);
    expect($p->evaluate(new ShieldState(), 'tab_hidden', 1)->action)->toBe('lock');
});
