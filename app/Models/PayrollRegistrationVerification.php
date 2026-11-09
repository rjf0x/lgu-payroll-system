<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['registration_id', 'verified_by', 'stored_hash', 'calculated_hash', 'captured_hash', 'verification_result', 'remarks', 'evidence', 'verified_at'])]
class PayrollRegistrationVerification extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'verification_id';

    public const MATCH = 'MATCH';

    public const MISMATCH = 'MISMATCH';

    public const PENDING = 'PENDING';

    public const NOT_REGISTERED = 'NOT_REGISTERED';

    public const UNAVAILABLE = 'UNAVAILABLE';

    public const INVALID_EVIDENCE = 'INVALID_EVIDENCE';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['evidence' => 'array', 'verified_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(PayrollRegistration::class, 'registration_id', 'registration_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }
}
