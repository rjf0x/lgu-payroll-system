<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class PayrollCalculationItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['entry_snapshot' => 'array', 'input_snapshot' => 'array', 'breakdown' => 'array'];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Saved calculation items are immutable.');
        static::updating($reject);
        static::deleting($reject);
    }
}
