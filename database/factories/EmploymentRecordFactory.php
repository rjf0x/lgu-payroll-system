<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentRecord>
 */
class EmploymentRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'employment_type_id' => EmploymentType::factory(),
            'appointment_number' => fake()->optional()->bothify('APPT-####'),
            'start_date' => '2024-01-01',
            'end_date' => null,
            'employment_status' => EmploymentRecord::STATUS_ACTIVE,
        ];
    }
}
