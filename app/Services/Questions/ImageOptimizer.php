<?php

namespace App\Services\Questions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Optimasi gambar soal dengan GD (tanpa imagick): sisi terpanjang maks 1600 px, disimpan WebP
 * di storage privat `media/questions/` (tidak ada folder publik berisi semua gambar soal).
 * Mengembalikan path relatif terhadap folder media, mis. "questions/ab12.webp".
 */
final class ImageOptimizer
{
    public const MAX_SIDE = 1600;
    public const MAX_BYTES = 2 * 1024 * 1024;

    public function storeFromString(string $bytes, string $dir = 'questions'): string
    {
        if (strlen($bytes) > self::MAX_BYTES * 4) {
            throw new RuntimeException('Gambar terlalu besar.');
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new RuntimeException('Format gambar harus JPG, PNG, WEBP, atau GIF.');
        }
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            throw new RuntimeException('Gambar tidak dapat dibaca.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        $ok = function_exists('imagewebp') ? imagewebp($dst, null, 82) : imagepng($dst);
        $data = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        if (! $ok || $data === '') {
            throw new RuntimeException('Gagal mengonversi gambar.');
        }
        $ext = function_exists('imagewebp') ? 'webp' : 'png';
        $path = trim($dir, '/') . '/' . date('Ym') . '/' . Str::random(24) . '.' . $ext;
        Storage::disk('local')->put('media/' . $path, $data);

        return $path;
    }

    public function storeUpload($upload, string $dir = 'questions'): string
    {
        return $this->storeFromString($upload->get(), $dir);
    }

    public static function adminUrl(?string $path): ?string
    {
        return $path ? '/admin/media/' . ltrim($path, '/') : null;
    }
}
