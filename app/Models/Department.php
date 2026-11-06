<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['department_code', 'department_name', 'description', 'status'])]
class Department extends Model
{
    use HasFactory;

    protected $primaryKey = 'department_id';

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'department_id', 'department_id');
    }

    public function employmentRecords(): HasMany
    {
        return $this->hasMany(EmploymentRecord::class, 'department_id', 'department_id');
    }
}
