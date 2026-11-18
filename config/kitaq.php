<?php

/*
|--------------------------------------------------------------------------
| KITAQ ONLINE — KITA Assessment & Question (versi online)
|--------------------------------------------------------------------------
| Produk CiptaKita.id. Identitas SEKOLAH tidak di sini, melainkan di tabel
| `settings` (menu Pengaturan). Nilai per ujian (profil keketatan, pengecualian,
| browser yang diizinkan, mode kanvas) disimpan di kolom JSON `exams.settings`
| dan menimpa nilai default di bawah.
*/

return [

    'name' => 'KITAQ ONLINE',
    'full_name' => 'KITA Assessment & Question — Online',
    'version' => env('KITAQ_VERSION', '1.0.0'),
    'vendor' => 'CiptaKita.id',
    'vendor_tagline' => 'Solusi Digitalmu, dari kita!',
    'vendor_url' => 'https://ciptakita.id',

    'brand' => [
        'navy' => '#022684',
        'red' => '#F92019',
        'blue' => '#0271D3',
        'logo' => 'images/kitaq-online-logo.png',
        'logo_dark' => 'images/kitaq-online-logo-dark.png',
        'mark' => 'images/ciptakita-mark.png',
    ],

    /*
    | Login siswa: NISN + tanggal lahir (DDMMYYYY).
    */
    'student_login' => [
        'nisn_length' => 10,
        'password_format' => 'dmY',                                   // 20 Oktober 2008 → 20102008
        'force_change_password' => (bool) env('KITAQ_FORCE_CHANGE_PASSWORD', false),
        'max_attempts_per_minute' => (int) env('KITAQ_LOGIN_PER_MINUTE', 5),   // per NISN + IP
        'lockout_after_failures' => (int) env('KITAQ_LOGIN_LOCKOUT', 10),      // akun dibekukan sementara
        'lockout_minutes' => (int) env('KITAQ_LOGIN_LOCKOUT_MINUTES', 15),
        'single_session' => true,                                     // login baru menendang sesi lama di luar ujian
    ],

    /*
    | Token ujian berganti otomatis (ditampilkan di layar pengawas).
    */
    'exam_token' => [
        'enabled_default' => true,
        'rotation_sec' => (int) env('KITAQ_TOKEN_ROTATION', 900),
        'length' => 6,
        'grace_windows' => 1,
    ],

    /*
    | KITAQ Shield — anti-contek. Lihat docs/ANTI_CONTEK.md.
    */
    'shield' => [
        'preset' => env('KITAQ_SHIELD_PRESET', 'standar'),            // longgar | standar | ketat
        'max_warnings' => (int) env('KITAQ_MAX_WARNINGS', 2),         // pelanggaran ke-3 mengunci
        'merge_window_sec' => 3,
        'blur_grace_ms' => 1200,
        'split_min_width_ratio' => 0.80,
        'split_min_height_ratio' => 0.62,
        'require_fullscreen' => true,                                 // Android, HarmonyOS, iPad, desktop
        'ios_require_standalone' => true,                             // iPhone: wajib dibuka dari ikon Layar Utama
        'physical_keyboard' => ['mobile' => 'block', 'desktop' => 'allow'],
        'canvas_mode' => false,                                       // Mode Soal Kanvas (ketat+)
        'browsers' => ['chrome', 'safari', 'samsung', 'huawei'],      // edge/opera/brave punya asisten AI bawaan
        'ai_signatures' => [
            'grammarly-desktop-integration', '[data-grammarly-shadow-root]', 'grammarly-extension',
            '#monica-content-root', '[id^="monica-"]', 'monica-shadow-host',
            '[id^="sider-"]', 'sider-quick-compose', '#merlin-root', '[id^="merlin"]',
            '#maxai-root', '[id^="maxai"]', '[id^="quillbot"]', 'quillbot-extension-portal',
            '[class*="chatgpt-sidebar"]', '#chatgpt-sidebar', '[id^="harpa"]', '#glasp-root',
            '[id*="ai-assistant-ext"]',
            'iframe[src^="chrome-extension://"]', 'iframe[src^="moz-extension://"]',
            'iframe[src^="safari-web-extension://"]', 'link[href^="chrome-extension://"]',
        ],
        'heartbeat_sec' => (int) env('KITAQ_HEARTBEAT_SEC', 15),
        'heartbeat_gap_sec' => (int) env('KITAQ_HEARTBEAT_GAP_SEC', 60),     // tanpa heartbeat selama ini → dicatat
        'offline_lock_after_sec' => (int) env('KITAQ_OFFLINE_LOCK_SEC', 0),  // 0 = jangan pernah kunci karena offline
    ],

    /*
    | Realtime di shared hosting: polling HTTP (tanpa WebSocket).
    */
    'realtime' => [
        'driver' => env('KITAQ_REALTIME', 'polling'),
        'monitor_poll_sec' => (int) env('KITAQ_MONITOR_POLL_SEC', 4),
        'monitor_cache_sec' => 2,                                     // ringankan beban saat banyak pengawas membuka monitoring
    ],

    /*
    | Lokasi ujian (opsional, per ujian): batasi ke IP publik sekolah.
    */
    'location' => [
        'ip_allowlist' => array_filter(array_map('trim', explode(',', (string) env('KITAQ_IP_ALLOWLIST', '')))),
        'trust_proxies' => env('KITAQ_TRUST_PROXIES', '*'),          // shared hosting di balik proxy/CDN
    ],

    /*
    | Mode uji beban (k6): menonaktifkan gerbang browser agar skrip k6 bisa login.
    | JANGAN aktifkan saat ujian sungguhan.
    */
    'load_test' => (bool) env('KITAQ_LOAD_TEST', false),

    'install_token' => env('KITAQ_INSTALL_TOKEN'),
    'admin_idle_minutes' => (int) env('KITAQ_ADMIN_IDLE_MINUTES', 120),
    'backup_retention_days' => (int) env('KITAQ_BACKUP_RETENTION_DAYS', 14),

    'privacy' => [
        'retention_days_logs' => (int) env('KITAQ_LOG_RETENTION_DAYS', 365),
        'mask_birthdate_in_ui' => true,
    ],
];
