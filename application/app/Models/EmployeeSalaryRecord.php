<?php

namespace App\Models;

use App\Enums\PersonnelClassification;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Guarded(['*'])]
class EmployeeSalaryRecord extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'step_basis_date' => 'date', 'monthly_salary' => 'decimal:2', 'is_demonstration' => 'boolean', 'personnel_classification' => PersonnelClassification::class, 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Employee salary history is immutable. Record a new authorized change.');
        static::updating($reject);
        static::deleting($reject);
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(SalaryRate::class, 'salary_rate_id');
    }
}
