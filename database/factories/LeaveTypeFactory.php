<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'default_credit' => 15.00,
            'is_paid' => true,
            'status' => 'ACTIVE',
        ];
    }
}
