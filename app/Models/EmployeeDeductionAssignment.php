<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employment_id',
    'deduction_type_id',
    'amount',
    'percentage',
    'reference_number',
    'effective_from',
    'effective_to',
    'status',
])]
class EmployeeDeductionAssignment extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'assignment_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'percentage' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employmentRecord(): BelongsTo
    {
        return $this->belongsTo(EmploymentRecord::class, 'employment_id', 'employment_id');
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id', 'deduction_type_id');
    }
}
