<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class StatutoryPreview extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['revision' => 'integer', 'inputs' => 'array', 'parameters' => 'array', 'result' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Statutory previews are immutable. Save a new reviewed scenario.');
        static::updating($reject);
        static::deleting($reject);
    }
}
