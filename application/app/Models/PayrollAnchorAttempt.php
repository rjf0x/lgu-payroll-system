<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class PayrollAnchorAttempt extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Only the service may complete its reserved attempt, with a conditional update.
        $reject = fn () => throw new LogicException('Payroll anchor attempts are retained.');
        static::updating($reject);
        static::deleting($reject);
    }
}
