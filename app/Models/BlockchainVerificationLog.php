<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'anchor_id',
    'verified_by',
    'stored_hash',
    'calculated_hash',
    'verification_result',
    'remarks',
    'verified_at',
])]
class BlockchainVerificationLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'verification_id';

    public const RESULT_MATCH = 'MATCH';

    public const RESULT_MISMATCH = 'MISMATCH';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function anchor(): BelongsTo
    {
        return $this->belongsTo(BlockchainAnchor::class, 'anchor_id', 'anchor_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }
}
