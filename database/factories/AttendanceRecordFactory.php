<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'attendance_date' => fake()->unique()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'attendance_status' => AttendanceRecord::STATUS_PRESENT,
            'credited_days' => 1.00,
            'source' => 'MANUAL',
            'remarks' => null,
        ];
    }
}
