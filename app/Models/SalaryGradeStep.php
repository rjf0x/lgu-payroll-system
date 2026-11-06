<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['salary_schedule_id', 'grade_number', 'step_number', 'monthly_salary'])]
class SalaryGradeStep extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'grade_step_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
        ];
    }

    public function salarySchedule(): BelongsTo
    {
        return $this->belongsTo(SalarySchedule::class, 'salary_schedule_id', 'salary_schedule_id');
    }

    public function compensationProfiles(): HasMany
    {
        return $this->hasMany(CompensationProfile::class, 'grade_step_id', 'grade_step_id');
    }
}
