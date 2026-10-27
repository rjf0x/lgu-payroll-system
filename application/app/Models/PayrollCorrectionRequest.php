<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Guarded(['*'])]
class PayrollCorrectionRequest extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['revision' => 'integer', 'created_at' => 'datetime'];
    }

    public function decision(): HasOne
    {
        return $this->hasOne(PayrollCorrectionDecision::class);
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Payroll correction requests are immutable. Return and request a new revision.');
        static::updating($reject);
        static::deleting($reject);
    }
}
