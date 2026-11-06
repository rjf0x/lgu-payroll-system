<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_detail_id',
    'contribution_rate_id',
    'contribution_type_snapshot',
    'amount',
])]
class PayrollEmployerContribution extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'employer_contribution_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payrollDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollDetail::class, 'payroll_detail_id', 'payroll_detail_id');
    }

    public function contributionRate(): BelongsTo
    {
        return $this->belongsTo(ContributionRate::class, 'contribution_rate_id', 'contribution_rate_id');
    }
}
