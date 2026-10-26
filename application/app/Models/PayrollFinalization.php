<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class PayrollFinalization extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['version' => 'integer', 'snapshot' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Finalized payroll snapshots are immutable.');
        static::updating($reject);
        static::deleting($reject);
    }
}
