<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class PayrollSnapshotArchive extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Preserved canonical payroll bytes are immutable.');
        static::updating($reject);
        static::deleting($reject);
    }
}
