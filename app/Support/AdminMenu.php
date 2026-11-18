<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Inventaris menu admin (DESIGN_SYSTEM 3.1) yang difilter per role:
 * Guru → Bank Soal, Ujian miliknya, Hasil mapelnya; Pengawas → Monitoring & Jadwal hari ini.
 */
final class AdminMenu
{
    /** @return list<array{label:string,items:list<array{label:string,route:string,icon:string,match:string}>}> */
    public static function for(User $user): array
    {
        $all = 'super_admin|operator|guru|pengawas';
        $admin = 'super_admin|operator';
        $teach = 'super_admin|operator|guru';

        $groups = [
            'UTAMA' => [
                ['Dashboard', 'admin.dashboard', 'dash', 'admin.dashboard', $all],
                ['Monitoring Live', 'admin.monitoring.index', 'activity', 'admin.monitoring.*', $all],
                ['Sesi & Token', 'admin.sessions', 'key', 'admin.sessions', $all],
            ],
            'AKADEMIK' => [
                ['Siswa', 'admin.students', 'users', 'admin.students*', $admin],
                ['Kelas', 'admin.classrooms', 'school', 'admin.classrooms', $admin],
                ['Mata Pelajaran', 'admin.subjects', 'book', 'admin.subjects', $admin],
                ['Pengguna', 'admin.users', 'user', 'admin.users*', 'super_admin'],
                ['Ruang & Sesi', 'admin.rooms', 'grid', 'admin.rooms', $admin],
                ['Tahun Ajaran', 'admin.academic-years', 'cal', 'admin.academic-years', $admin],
            ],
            'UJIAN' => [
                ['Bank Soal', 'admin.banks', 'fq', 'admin.banks*', $teach],
                ['Ujian', 'admin.exams', 'pencil', 'admin.exams*', $teach],
                ['Jadwal', 'admin.schedules', 'clock', 'admin.schedules*', $all],
                ['Koreksi Uraian', 'admin.grading', 'check', 'admin.grading*', $teach],
            ],
            'LAPORAN' => [
                ['Rekap Nilai', 'admin.recap', 'bars', 'admin.recap', $teach],
                ['Analisis Butir', 'admin.item-analysis', 'trend', 'admin.item-analysis', $teach],
                ['Pelanggaran', 'admin.violations', 'shield', 'admin.violations', $all],
                ['Analitik Integritas', 'admin.integrity', 'flag', 'admin.integrity', $teach],
                ['Berita Acara', 'admin.minutes', 'file', 'admin.minutes*', $all],
                ['Unduhan', 'admin.exports', 'download', 'admin.exports', $teach],
            ],
            'SISTEM' => [
                ['Pengaturan Sekolah', 'admin.settings', 'sliders', 'admin.settings', 'super_admin'],
                ['Kesehatan Sistem', 'admin.health', 'heart', 'admin.health', $admin],
                ['Backup', 'admin.backups', 'database', 'admin.backups', 'super_admin'],
                ['Log Aktivitas', 'admin.activity', 'monitor', 'admin.activity', $admin],
                ['Tentang KITAQ', 'admin.about', 'info', 'admin.about', $all],
            ],
        ];

        $out = [];
        foreach ($groups as $label => $items) {
            $visible = [];
            foreach ($items as [$text, $route, $icon, $match, $roles]) {
                if (! Route::has($route) || ! $user->hasAnyRole(explode('|', $roles))) {
                    continue;
                }
                $visible[] = ['label' => $text, 'route' => $route, 'icon' => $icon, 'match' => $match];
            }
            if ($visible) {
                $out[] = ['label' => $label, 'items' => $visible];
            }
        }

        return $out;
    }

    /** Item command palette (halaman + aksi cepat). @return list<array{label:string,group:string,url:string}> */
    public static function paletteItems(User $user): array
    {
        $items = [];
        foreach (self::for($user) as $g) {
            foreach ($g['items'] as $i) {
                $items[] = ['label' => $i['label'], 'group' => 'Halaman', 'url' => route($i['route'])];
            }
        }
        $actions = [
            ['Tambah siswa', 'admin.students', ['create' => 1], 'super_admin|operator'],
            ['Import siswa dari Excel', 'admin.students.import', [], 'super_admin|operator'],
            ['Buat ujian baru', 'admin.exams', ['create' => 1], 'super_admin|operator|guru'],
            ['Buat jadwal ujian', 'admin.schedules', ['create' => 1], 'super_admin|operator'],
            ['Unduh template siswa', 'admin.templates', ['file' => 'siswa'], 'super_admin|operator'],
            ['Unduh template soal', 'admin.templates', ['file' => 'soal'], 'super_admin|operator|guru'],
            ['Ganti password saya', 'admin.profile', [], 'super_admin|operator|guru|pengawas'],
        ];
        foreach ($actions as [$label, $route, $params, $roles]) {
            if (Route::has($route) && $user->hasAnyRole(explode('|', $roles))) {
                $items[] = ['label' => $label, 'group' => 'Aksi', 'url' => route($route, $params)];
            }
        }

        return $items;
    }
}
