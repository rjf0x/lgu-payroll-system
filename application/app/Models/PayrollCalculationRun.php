<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Guarded(['*'])]
class PayrollCalculationRun extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['batch_version' => 'integer', 'totals' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Calculation runs are immutable. Create a new run after reviewing draft changes.');
        static::updating($reject);
        static::deleting($reject);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollCalculationItem::class);
    }
}
