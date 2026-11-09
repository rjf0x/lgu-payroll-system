<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['payroll_run_id', 'organization_id', 'payroll_reference', 'revision', 'hash_schema_version', 'payload_hash',
    'idempotency_key', 'canonical_payload', 'period_snapshot', 'approval_snapshot', 'target_network', 'status',
    'transaction_hash', 'evidence', 'attempts', 'last_error', 'next_attempt_at', 'lease_token'])]
class PayrollRegistration extends Model
{
    protected $primaryKey = 'registration_id';

    protected $hidden = ['canonical_payload', 'period_snapshot', 'approval_snapshot', 'target_network'];

    public const QUEUED = 'QUEUED';

    public const SUBMITTED = 'SUBMITTED';

    public const RECONCILING = 'RECONCILING';

    public const CONFIRMED = 'CONFIRMED';

    public const FAILED = 'FAILED';

    public const CONFLICT = 'CONFLICT';

    public const ACTIVE = [self::QUEUED, self::SUBMITTED, self::RECONCILING];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'hash_schema_version' => 'integer', 'attempts' => 'integer', 'period_snapshot' => 'array',
            'approval_snapshot' => 'array', 'target_network' => 'array', 'evidence' => 'array', 'next_attempt_at' => 'datetime'];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'payroll_run_id');
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(PayrollRegistrationVerification::class, 'registration_id', 'registration_id');
    }

    public function latestVerification(): HasOne
    {
        return $this->hasOne(PayrollRegistrationVerification::class, 'registration_id', 'registration_id')->latestOfMany('verification_id');
    }

    /** @return array{organization_id:string, payroll_reference:string, revision:int, payload_hash:string, hash_schema_version:int, idempotency_key:string} */
    public function bridgeRequest(): array
    {
        return $this->only(['organization_id', 'payroll_reference', 'revision', 'payload_hash', 'hash_schema_version', 'idempotency_key']);
    }
}
