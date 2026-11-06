<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['deduction_name', 'deduction_category', 'computation_type', 'is_statutory', 'is_pre_tax', 'status'])]
class DeductionType extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'deduction_type_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_statutory' => 'boolean',
            'is_pre_tax' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeDeductionAssignment::class, 'deduction_type_id', 'deduction_type_id');
    }

    public function payrollDeductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class, 'deduction_type_id', 'deduction_type_id');
    }
}
