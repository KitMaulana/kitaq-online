<?php

use App\Services\Auth\StudentCredential;

beforeEach(function () {
    $this->today = new DateTimeImmutable('2026-11-18');
});

it('menormalkan NISN dan memulihkan nol di depan dari Excel', function () {
    expect(StudentCredential::normalizeNisn('0081234567'))->toBe('0081234567');
    expect(StudentCredential::normalizeNisn(81234567))->toBe('0081234567');
    expect(StudentCredential::normalizeNisn(' 008-123-4567 '))->toBe('0081234567');
    expect(StudentCredential::normalizeNisn(81234567.0))->toBe('0081234567');
    expect(StudentCredential::normalizeNisn('12345'))->toBeNull();
    expect(StudentCredential::normalizeNisn('123456789012'))->toBeNull();
    expect(StudentCredential::normalizeNisn(null))->toBeNull();
});

it('membaca tanggal lahir dari berbagai format', function (mixed $raw) {
    $d = StudentCredential::parseBirthDate($raw, $this->today);
    expect($d)->not->toBeNull();
    expect($d->format('Y-m-d'))->toBe('2008-10-20');
})->with([
    '2008-10-20', '2008/10/20', '20/10/2008', '20-10-2008', '20.10.2008', '20102008', '20081020',
    '20 Oktober 2008', '20 Okt 2008', '20-Okt-2008', '20 October 2008', '2008-10-20 00:00:00', 39741, '39741',
]);

it('menolak tanggal yang tidak valid atau tidak wajar', function (mixed $raw) {
    expect(StudentCredential::parseBirthDate($raw, $this->today))->toBeNull();
})->with(['31/02/2008', '2008-13-01', '', 'besok', '01/01/1900', '01/01/2030', null, '20 Brumaire 2008']);

it('membentuk password DDMMYYYY dari tanggal lahir', function () {
    $d = StudentCredential::parseBirthDate('1 Feb 2009', $this->today);
    expect(StudentCredential::passwordFromBirthDate($d))->toBe('01022009');
});

it('merapikan input password siswa', function (string $input, string $expected) {
    expect(StudentCredential::normalizePasswordInput($input))->toBe($expected);
})->with([
    ['20102008', '20102008'],
    ['20-10-2008', '20102008'],
    ['20/10/2008', '20102008'],
    ['20 10 2008', '20102008'],
    ['1-2-2009', '01022009'],
    ['2008-10-20', '20102008'],
    [' 20102008 ', '20102008'],
    ['RahasiaBaru#1', 'RahasiaBaru#1'],
]);
