<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class LedgerVerification extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'records_checked' => 'integer',
            'issues_found' => 'integer',
            'report' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Ledger verification history is immutable.');
        static::updating($reject);
        static::deleting($reject);
    }
}
