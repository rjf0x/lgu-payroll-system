<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class BesuAnchorTransaction extends Model
{
    protected $hidden = ['encrypted_transaction'];

    protected function casts(): array
    {
        return ['identity' => 'array', 'nonce' => 'integer', 'block_number' => 'integer', 'confirmed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Transaction history is service-managed.'));
        static::deleting(fn () => throw new LogicException('Transaction history is retained.'));
    }
}
