<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['department_id', 'position_code', 'position_title', 'salary_grade', 'description', 'status'])]
class Position extends Model
{
    use HasFactory;

    protected $primaryKey = 'position_id';

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function employmentRecords(): HasMany
    {
        return $this->hasMany(EmploymentRecord::class, 'position_id', 'position_id');
    }
}
