<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payroll_run_id',
    'payload_hash',
    'hash_algorithm',
    'transaction_id',
    'block_number',
    'network_name',
    'contract_address',
    'anchor_status',
    'anchored_by',
    'anchored_at',
])]
class BlockchainAnchor extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'anchor_id';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ANCHORED = 'ANCHORED';

    public const STATUS_FAILED = 'FAILED';

    public const ALGORITHM_SHA256 = 'SHA-256';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anchored_at' => 'datetime',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'payroll_run_id');
    }

    public function anchoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anchored_by', 'user_id');
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(BlockchainVerificationLog::class, 'anchor_id', 'anchor_id');
    }
}
