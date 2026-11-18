<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSession extends Model
{
    protected $fillable = ['name', 'starts_at', 'ends_at'];

    public function label(): string
    {
        if (! $this->starts_at) {
            return $this->name;
        }

        return $this->name . ' (' . substr((string) $this->starts_at, 0, 5) . '-' . substr((string) $this->ends_at, 0, 5) . ')';
    }
}
