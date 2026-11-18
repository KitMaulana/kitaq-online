<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Pengaturan sekolah (kunci → nilai JSON). Dibaca lewat Setting::get() dengan cache. */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'school_name' => 'Nama Sekolah',
        'school_address' => '',
        'school_city' => '',
        'school_logo' => null,
        'school_letterhead' => '',
        'principal_name' => '',
        'principal_nip' => '',
        'site_url' => null,
        'force_change_password' => false,
        'default_preset' => 'standar',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::allCached();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        Cache::forget('kitaq:settings');
    }

    /** @return array<string,mixed> */
    public static function allCached(): array
    {
        try {
            return Cache::remember('kitaq:settings', 300, function () {
                return self::query()->pluck('value', 'key')->map(fn ($v) => json_decode((string) $v, true))->all();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }
}
