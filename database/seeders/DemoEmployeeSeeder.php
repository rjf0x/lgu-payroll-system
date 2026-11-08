<?php

namespace Database\Seeders;

use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoEmployeeSeeder extends Seeder
{
    /**
     * Seed fictional employees after organization and salary master data exist.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Fictional employees are restricted to local and testing environments.');
        }

        $positions = Position::all()->keyBy('position_code');
        $schedule = SalarySchedule::where('schedule_name', 'LGU Salary Schedule (Illustrative)')->firstOrFail();
        $gradeSteps = SalaryGradeStep::where('salary_schedule_id', $schedule->getKey())
            ->where('step_number', 1)->get()->keyBy('grade_number');

        $permanent = EmploymentType::where('employment_type_name', EmploymentType::PERMANENT)->firstOrFail();
        $coterminous = EmploymentType::where('employment_type_name', EmploymentType::COTERMINOUS)->firstOrFail();
        $partTime = EmploymentType::where('employment_type_name', EmploymentType::PART_TIME)->firstOrFail();

        $gradeBased = [
            ['EMP-00001', 'Maria', 'Santos', 'Dela Cruz', 'FEMALE', 'MARRIED', 'TREASURER'],
            ['EMP-00002', 'Jose', 'Reyes', 'Bautista', 'MALE', 'MARRIED', 'ACCOUNTANT'],
            ['EMP-00003', 'Ana', 'Lopez', 'Villamor', 'FEMALE', 'SINGLE', 'HRO'],
            ['EMP-00004', 'Pedro', null, 'Gomez', 'MALE', 'MARRIED', 'COLLECTION-CLERK'],
            ['EMP-00005', 'Liza', 'Cruz', 'Ramos', 'FEMALE', 'SINGLE', 'ACCT-CLERK'],
            ['EMP-00006', 'Ramon', null, 'Aquino', 'MALE', 'SINGLE', 'ADMIN-AIDE'],
        ];

        foreach ($gradeBased as [$number, $first, $middle, $last, $sex, $civil, $positionCode]) {
            $employee = Employee::firstOrCreate(
                ['employee_number' => $number],
                [
                    'first_name' => $first,
                    'middle_name' => $middle,
                    'last_name' => $last,
                    'birth_date' => '1990-01-01',
                    'sex' => $sex,
                    'civil_status' => $civil,
                    'email' => strtolower($first).'.'.strtolower($last).'@lgu.gov.ph',
                    'contact_number' => '0917-000-0000',
                    'address' => 'Poblacion, Municipality',
                ],
            );

            $position = $positions[$positionCode];

            $record = EmploymentRecord::firstOrCreate(
                ['employee_id' => $employee->employee_id, 'position_id' => $position->position_id],
                [
                    'department_id' => $position->department_id,
                    'employment_type_id' => $permanent->employment_type_id,
                    'start_date' => '2024-01-01',
                    'end_date' => null,
                    'employment_status' => EmploymentRecord::STATUS_ACTIVE,
                ],
            );

            $gradeStep = $gradeSteps[$position->salary_grade] ?? null;

            if ($gradeStep !== null) {
                CompensationProfile::whereDate('effective_from', '2024-01-01')->firstOrCreate(
                    ['employment_id' => $record->employment_id],
                    [
                        'effective_from' => '2024-01-01',
                        'grade_step_id' => $gradeStep->grade_step_id,
                        'pay_basis' => CompensationProfile::BASIS_MONTHLY,
                        'basic_rate' => null,
                        'service_fraction' => 1.0000,
                        'effective_to' => null,
                    ],
                );
            }
        }

        // Coterminous employee on an approved direct monthly rate.
        $this->seedDirectRateEmployee(
            ['EMP-00007', 'Carla', null, 'Mendoza', 'FEMALE', 'SINGLE'],
            $positions['HRO'],
            $coterminous,
            CompensationProfile::BASIS_MONTHLY,
            30000.00,
            1.0000,
        );

        // Part-time employee on an approved daily rate.
        $this->seedDirectRateEmployee(
            ['EMP-00008', 'Nico', null, 'Salazar', 'MALE', 'SINGLE'],
            $positions['ACCT-CLERK'],
            $partTime,
            CompensationProfile::BASIS_DAILY,
            800.00,
            0.5000,
        );
    }

    /**
     * @param  array{0: string, 1: string, 2: ?string, 3: string, 4: string, 5: string}  $name
     */
    private function seedDirectRateEmployee(
        array $name,
        Position $position,
        EmploymentType $employmentType,
        string $payBasis,
        float $rate,
        float $serviceFraction,
    ): void {
        [$number, $first, $middle, $last, $sex, $civil] = $name;

        $employee = Employee::firstOrCreate(
            ['employee_number' => $number],
            [
                'first_name' => $first,
                'middle_name' => $middle,
                'last_name' => $last,
                'birth_date' => '1992-06-15',
                'sex' => $sex,
                'civil_status' => $civil,
                'email' => strtolower($first).'.'.strtolower($last).'@lgu.gov.ph',
                'contact_number' => '0917-000-0000',
                'address' => 'Poblacion, Municipality',
            ],
        );

        $record = EmploymentRecord::firstOrCreate(
            ['employee_id' => $employee->employee_id, 'position_id' => $position->position_id],
            [
                'department_id' => $position->department_id,
                'employment_type_id' => $employmentType->employment_type_id,
                'start_date' => '2024-01-01',
                'end_date' => null,
                'employment_status' => EmploymentRecord::STATUS_ACTIVE,
            ],
        );

        CompensationProfile::whereDate('effective_from', '2024-01-01')->firstOrCreate(
            ['employment_id' => $record->employment_id],
            [
                'effective_from' => '2024-01-01',
                'grade_step_id' => null,
                'pay_basis' => $payBasis,
                'basic_rate' => $rate,
                'service_fraction' => $serviceFraction,
                'effective_to' => null,
            ],
        );
    }
}
