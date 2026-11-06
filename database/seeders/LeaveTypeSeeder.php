<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Seed common leave types. Credit values are placeholders pending client confirmation.
     */
    public function run(): void
    {
        $leaveTypes = [
            ['leave_name' => 'Vacation Leave', 'default_credit' => 15.00, 'is_paid' => true],
            ['leave_name' => 'Sick Leave', 'default_credit' => 15.00, 'is_paid' => true],
            ['leave_name' => 'Maternity Leave', 'default_credit' => 105.00, 'is_paid' => true],
            ['leave_name' => 'Paternity Leave', 'default_credit' => 7.00, 'is_paid' => true],
            ['leave_name' => 'Special Privilege Leave', 'default_credit' => 3.00, 'is_paid' => true],
            ['leave_name' => 'Leave Without Pay', 'default_credit' => 0.00, 'is_paid' => false],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::updateOrCreate(
                ['leave_name' => $leaveType['leave_name']],
                [
                    'default_credit' => $leaveType['default_credit'],
                    'is_paid' => $leaveType['is_paid'],
                    'status' => 'ACTIVE',
                ],
            );
        }
    }
}
