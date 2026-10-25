<?php

namespace App\Models;

use App\Enums\PersonnelClassification;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['employee_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'position', 'office', 'personnel_classification', 'employment_start_date', 'is_active'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true, 'record_version' => 1];

    protected function casts(): array
    {
        return [
            'personnel_classification' => PersonnelClassification::class,
            'employment_start_date' => 'date',
            'is_active' => 'boolean',
            'record_version' => 'integer',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => implode(' ', array_filter([
            $this->first_name, $this->middle_name, $this->last_name, $this->suffix,
        ], fn ($part) => $part !== null && $part !== '')));
    }
}
