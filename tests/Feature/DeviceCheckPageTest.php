<?php

it('cek perangkat: skrip inline memakai nonce yang sama dengan header CSP', function () {
    $res = $this->get('/cek-perangkat')->assertOk();

    preg_match("/script-src[^;]*'nonce-([^']+)'/", $res->headers->get('Content-Security-Policy'), $m);
    expect($m[1] ?? null)->not->toBeNull();

    $html = $res->getContent();
    expect($html)->toContain('<script nonce="' . $m[1] . '">')
        ->and(preg_match('/<script(?![^>]*nonce=)/i', $html))->toBe(0);
});

it('cek perangkat: /api/ping publik, ringan, dan tidak di-cache', function () {
    $this->get('/api/ping')->assertNoContent()->assertHeader('Cache-Control', 'no-store, private');
});
