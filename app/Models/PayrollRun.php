<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'payroll_period_id',
    'run_number',
    'run_type',
    'status',
    'created_by',
    'processed_at',
    'submitted_by',
    'submitted_at',
    'finalized_by',
    'finalized_at',
])]
class PayrollRun extends Model
{
    use HasFactory;

    protected $primaryKey = 'payroll_run_id';

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const STATUS_RETURNED = 'RETURNED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_FINALIZED = 'FINALIZED';

    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * A run may be edited only while it is a draft, returned, or rejected.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_RETURNED,
            self::STATUS_REJECTED,
        ], true);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id', 'payroll_period_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'user_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by', 'user_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PayrollApproval::class, 'payroll_run_id', 'payroll_run_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollDetail::class, 'payroll_run_id', 'payroll_run_id');
    }

    /**
     * Alias used by scoped route-model binding for `{payroll_detail}`.
     */
    public function payrollDetails(): HasMany
    {
        return $this->details();
    }

    public function anchor(): HasOne
    {
        return $this->hasOne(BlockchainAnchor::class, 'payroll_run_id', 'payroll_run_id');
    }
}
