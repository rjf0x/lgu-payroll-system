<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class PayrollSubmission extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['revision' => 'integer', 'batch_version' => 'integer', 'snapshot' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Payroll submissions are immutable. Return and resubmit a corrected revision.');
        static::updating($reject);
        static::deleting($reject);
    }
}
