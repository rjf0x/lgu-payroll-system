<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'department_id',
    'position_id',
    'employment_type_id',
    'appointment_number',
    'start_date',
    'end_date',
    'employment_status',
])]
class EmploymentRecord extends Model
{
    use HasFactory;

    protected $primaryKey = 'employment_id';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id')->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id', 'position_id');
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class, 'employment_type_id', 'employment_type_id');
    }

    public function compensationProfiles(): HasMany
    {
        return $this->hasMany(CompensationProfile::class, 'employment_id', 'employment_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EmploymentHistory::class, 'employment_id', 'employment_id');
    }

    public function earningAssignments(): HasMany
    {
        return $this->hasMany(EmployeeEarningAssignment::class, 'employment_id', 'employment_id');
    }

    public function deductionAssignments(): HasMany
    {
        return $this->hasMany(EmployeeDeductionAssignment::class, 'employment_id', 'employment_id');
    }

    public function payrollDetails(): HasMany
    {
        return $this->hasMany(PayrollDetail::class, 'employment_id', 'employment_id');
    }
}
