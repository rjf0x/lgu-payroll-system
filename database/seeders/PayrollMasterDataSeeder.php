<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollMasterDataSeeder extends Seeder
{
    /**
     * Seed illustrative organization, salary, employees, and workbook-based payroll types.
     * Salary amounts are fictional and are not an adopted LGU salary schedule.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Illustrative payroll master data is restricted to local and testing environments.');
        }

        DB::transaction(function (): void {
            $this->call([
                EmploymentTypeSeeder::class,
                EarningTypeSeeder::class,
                DeductionTypeSeeder::class,
                StatutoryConfigSeeder::class,
            ]);

            foreach ([
                'MO' => 'Office of the Municipal Mayor',
                'MTO' => "Municipal Treasurer's Office",
                'MACO' => 'Municipal Accounting Office',
                'HRMO' => 'Human Resource Management Office',
            ] as $code => $name) {
                Department::firstOrCreate(
                    ['department_code' => $code],
                    ['department_name' => $name, 'status' => 'ACTIVE'],
                );
            }

            $departments = Department::pluck('department_id', 'department_code');

            foreach ([
                ['MTO', 'TREASURER', 'Municipal Treasurer', 26],
                ['MACO', 'ACCOUNTANT', 'Municipal Accountant', 26],
                ['HRMO', 'HRO', 'Human Resource Officer', 18],
                ['MTO', 'COLLECTION-CLERK', 'Revenue Collection Clerk', 8],
                ['MACO', 'ACCT-CLERK', 'Accounting Clerk', 8],
                ['MO', 'ADMIN-AIDE', 'Administrative Aide', 4],
            ] as [$departmentCode, $code, $title, $grade]) {
                Position::firstOrCreate(
                    ['position_code' => $code],
                    [
                        'department_id' => $departments[$departmentCode],
                        'position_title' => $title,
                        'salary_grade' => $grade,
                        'status' => 'ACTIVE',
                    ],
                );
            }

            $schedule = SalarySchedule::firstOrCreate(
                ['schedule_name' => 'LGU Salary Schedule (Illustrative)'],
                [
                    'effective_from' => '2024-01-01',
                    'effective_to' => null,
                    'reference_document' => 'ILLUSTRATIVE-ONLY',
                    'status' => SalarySchedule::STATUS_ACTIVE,
                ],
            );

            foreach ([
                [4, 1, 16000.00],
                [8, 1, 22000.00],
                [15, 1, 45000.00],
                [15, 2, 47000.00],
                [18, 1, 55000.00],
                [26, 1, 95000.00],
            ] as [$grade, $step, $amount]) {
                SalaryGradeStep::firstOrCreate(
                    [
                        'salary_schedule_id' => $schedule->salary_schedule_id,
                        'grade_number' => $grade,
                        'step_number' => $step,
                    ],
                    ['monthly_salary' => $amount],
                );
            }

            $this->call(DemoEmployeeSeeder::class);
        });
    }
}
