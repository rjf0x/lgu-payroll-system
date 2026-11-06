<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['earning_name', 'earning_category', 'computation_type', 'is_taxable', 'is_recurring', 'status'])]
class EarningType extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'earning_type_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_taxable' => 'boolean',
            'is_recurring' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeEarningAssignment::class, 'earning_type_id', 'earning_type_id');
    }

    public function payrollEarnings(): HasMany
    {
        return $this->hasMany(PayrollEarning::class, 'earning_type_id', 'earning_type_id');
    }
}
