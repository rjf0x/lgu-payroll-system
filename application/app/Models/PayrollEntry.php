<?php

namespace App\Models;

use App\Enums\PersonnelClassification;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Guarded(['*'])]
class PayrollEntry extends Model
{
    protected static function booted(): void
    {
        $check = function (self $entry) {
            $batchIds = array_unique([$entry->payroll_batch_id, $entry->getRawOriginal('payroll_batch_id') ?? $entry->payroll_batch_id]);
            if (PayrollBatch::whereIn('id', $batchIds)->whereNotIn('status', ['draft', 'returned'])->exists()) {
                throw new LogicException('Payroll coverage is locked during review and after finalization.');
            }
        };
        static::saving($check);
        static::deleting($check);
    }

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
