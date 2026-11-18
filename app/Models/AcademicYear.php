<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable = ['name', 'semester', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function active(): ?self
    {
        return self::query()->where('is_active', true)->first();
    }

    public function label(): string
    {
        return $this->name . ' · ' . ucfirst($this->semester);
    }
}
