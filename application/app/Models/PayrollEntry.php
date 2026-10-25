<?php

namespace App\Models;

use App\Enums\PersonnelClassification;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['*'])]
class PayrollEntry extends Model
{
    protected function casts(): array
    {
        return ['monthly_salary' => 'decimal:2', 'grade' => 'integer', 'step' => 'integer', 'personnel_classification' => PersonnelClassification::class];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryRecord(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalaryRecord::class, 'employee_salary_record_id');
    }
}
