<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payroll_run_id', 'approval_step', 'action', 'acted_by', 'remarks', 'acted_at'])]
class PayrollApproval extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'approval_id';

    public const ACTION_SUBMITTED = 'SUBMITTED';

    public const ACTION_APPROVED = 'APPROVED';

    public const ACTION_REJECTED = 'REJECTED';

    public const ACTION_RETURNED = 'RETURNED';

    public const ACTION_FINALIZED = 'FINALIZED';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'payroll_run_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by', 'user_id');
    }
}
