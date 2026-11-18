<?php

use App\Services\ExamTokenService;

beforeEach(function () {
    $this->svc = new ExamTokenService('rahasia-uji', 900, 6);
});

it('membuat token 6 karakter dari alfabet tanpa huruf mirip', function () {
    $t = $this->svc->current('sesi-1', 1_800_000_000);
    expect(strlen($t))->toBe(6);
    expect($t)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ2-9]{6}$/');
});

it('stabil dalam satu jendela dan berganti di jendela berikutnya', function () {
    $base = 1_800_000_100 - (1_800_000_100 % 900);
    expect($this->svc->current('s', $base))->toBe($this->svc->current('s', $base + 899));
    expect($this->svc->current('s', $base))->not->toBe($this->svc->current('s', $base + 900));
});

it('berbeda untuk sesi berbeda dan secret berbeda', function () {
    $now = 1_800_000_000;
    expect($this->svc->current('sesi-A', $now))->not->toBe($this->svc->current('sesi-B', $now));
    expect((new ExamTokenService('lain'))->current('sesi-A', $now))->not->toBe($this->svc->current('sesi-A', $now));
});

it('menerima token jendela sebelumnya (grace) tetapi tidak yang lebih lama', function () {
    $now = 1_800_000_000;
    $prev = $this->svc->forWindow('s', $this->svc->window($now) - 1);
    $old = $this->svc->forWindow('s', $this->svc->window($now) - 2);
    expect($this->svc->verify($prev, 's', $now))->toBeTrue();
    expect($this->svc->verify($old, 's', $now))->toBeFalse();
    expect($this->svc->verify($prev, 's', $now, 0))->toBeFalse();
});

it('tidak peka huruf besar/kecil dan spasi', function () {
    $now = 1_800_000_000;
    $t = $this->svc->current('s', $now);
    expect($this->svc->verify(strtolower(substr($t, 0, 3)) . ' ' . substr($t, 3), 's', $now))->toBeTrue();
    expect($this->svc->verify('ABC', 's', $now))->toBeFalse();
});

it('menghitung sisa detik sebelum token berganti', function () {
    expect($this->svc->secondsUntilRotation(900 * 10 + 300))->toBe(600);
});

it('menolak secret kosong', function () {
    expect(fn () => new ExamTokenService(''))->toThrow(InvalidArgumentException::class);
});
