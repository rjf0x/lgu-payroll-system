<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'contribution_type',
    'minimum_compensation',
    'maximum_compensation',
    'employee_rate',
    'employer_rate',
    'employee_fixed_amount',
    'employer_fixed_amount',
    'effective_from',
    'effective_to',
])]
class ContributionRate extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'contribution_rate_id';

    public const TYPE_GSIS = 'GSIS';

    public const TYPE_PHILHEALTH = 'PhilHealth';

    public const TYPE_PAGIBIG = 'Pag-IBIG';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_compensation' => 'decimal:2',
            'maximum_compensation' => 'decimal:2',
            'employee_rate' => 'decimal:4',
            'employer_rate' => 'decimal:4',
            'employee_fixed_amount' => 'decimal:2',
            'employer_fixed_amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employerContributions(): HasMany
    {
        return $this->hasMany(PayrollEmployerContribution::class, 'contribution_rate_id', 'contribution_rate_id');
    }
}
