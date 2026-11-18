<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Perintah pengawas untuk HP siswa (pesan, tambah waktu, kumpulkan paksa) — diambil lewat heartbeat. */
class AttemptCommand extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['exam_attempt_id', 'type', 'payload', 'user_id', 'delivered_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
