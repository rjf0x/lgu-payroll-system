<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class PayrollWorkflowEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['batch_version' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Payroll workflow events are immutable.');
        static::updating($reject);
        static::deleting($reject);
    }
}
