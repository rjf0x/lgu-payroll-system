<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class PayrollVerification extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['confirmations' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Payroll verification history is immutable.');
        static::updating($reject);
        static::deleting($reject);
    }
}
