<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class BesuAnchor extends Model
{
    protected $table = 'besu_anchor_outbox';

    protected $hidden = ['encrypted_transaction'];

    protected function casts(): array
    {
        return ['identity' => 'array', 'nonce' => 'integer', 'block_number' => 'integer', 'confirmed_at' => 'datetime', 'retry_count' => 'integer', 'next_attempt_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Only the anchoring processor may update its journal.'));
        static::deleting(fn () => throw new LogicException('Blockchain journals are retained.'));
    }
}
