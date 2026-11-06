<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['schedule_name', 'effective_from', 'effective_to', 'reference_document', 'status'])]
class SalarySchedule extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'salary_schedule_id';

    public const STATUS_ACTIVE = 'ACTIVE';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function gradeSteps(): HasMany
    {
        return $this->hasMany(SalaryGradeStep::class, 'salary_schedule_id', 'salary_schedule_id');
    }
}
