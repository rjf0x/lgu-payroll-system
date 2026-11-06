<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employment_id',
    'grade_step_id',
    'pay_basis',
    'basic_rate',
    'service_fraction',
    'effective_from',
    'effective_to',
])]
class CompensationProfile extends Model
{
    use HasFactory;

    protected $primaryKey = 'compensation_id';

    public const BASIS_MONTHLY = 'MONTHLY';

    public const BASIS_DAILY = 'DAILY';

    public const BASIS_HOURLY = 'HOURLY';

    public const BASIS_PROPORTIONAL_MONTHLY = 'PROPORTIONAL_MONTHLY';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'basic_rate' => 'decimal:2',
            'service_fraction' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employmentRecord(): BelongsTo
    {
        return $this->belongsTo(EmploymentRecord::class, 'employment_id', 'employment_id');
    }

    public function gradeStep(): BelongsTo
    {
        return $this->belongsTo(SalaryGradeStep::class, 'grade_step_id', 'grade_step_id');
    }
}
