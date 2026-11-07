<?php

namespace Database\Seeders;

use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed an illustrative organizational structure, employees, and compensation.
     *
     * All names and peso amounts here are fictional demo data for the mock defense.
     * The salary amounts are NOT the LGU's adopted schedule and must never be
     * treated as official figures.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Fictional payroll data cannot be seeded in production.');
        }

        $departments = $this->seedDepartments();
        $gradeSteps = $this->seedSalarySchedule();
        $positions = $this->seedPositions($departments);

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
            $employee = Employee::updateOrCreate(
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

            $record = EmploymentRecord::updateOrCreate(
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
                CompensationProfile::updateOrCreate(
                    ['employment_id' => $record->employment_id, 'effective_from' => '2024-01-01'],
                    [
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
     * @return array<string, Department>
     */
    private function seedDepartments(): array
    {
        $definitions = [
            ['MO', 'Office of the Municipal Mayor'],
            ['MTO', "Municipal Treasurer's Office"],
            ['MACO', 'Municipal Accounting Office'],
            ['HRMO', 'Human Resource Management Office'],
        ];

        $departments = [];

        foreach ($definitions as [$code, $name]) {
            $departments[$code] = Department::updateOrCreate(
                ['department_code' => $code],
                ['department_name' => $name, 'status' => 'ACTIVE'],
            );
        }

        return $departments;
    }

    /**
     * @return array<int, SalaryGradeStep> Keyed by grade number.
     */
    private function seedSalarySchedule(): array
    {
        $schedule = SalarySchedule::updateOrCreate(
            ['schedule_name' => 'LGU Salary Schedule (Illustrative)'],
            [
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'reference_document' => 'ILLUSTRATIVE-ONLY',
                'status' => SalarySchedule::STATUS_ACTIVE,
            ],
        );

        // Illustrative amounts only, including the client's SG 15 / Step 1 example.
        $stepAmounts = [
            [4, 1, 16000.00],
            [8, 1, 22000.00],
            [15, 1, 45000.00],
            [15, 2, 47000.00],
            [18, 1, 55000.00],
            [26, 1, 95000.00],
        ];

        $gradeSteps = [];

        foreach ($stepAmounts as [$grade, $step, $amount]) {
            $gradeStep = SalaryGradeStep::updateOrCreate(
                [
                    'salary_schedule_id' => $schedule->salary_schedule_id,
                    'grade_number' => $grade,
                    'step_number' => $step,
                ],
                ['monthly_salary' => $amount],
            );
            if ($step === 1) {
                $gradeSteps[$grade] = $gradeStep;
            }
        }

        return $gradeSteps;
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, Position>
     */
    private function seedPositions(array $departments): array
    {
        $definitions = [
            ['MTO', 'TREASURER', 'Municipal Treasurer', 26],
            ['MACO', 'ACCOUNTANT', 'Municipal Accountant', 26],
            ['HRMO', 'HRO', 'Human Resource Officer', 18],
            ['MTO', 'COLLECTION-CLERK', 'Revenue Collection Clerk', 8],
            ['MACO', 'ACCT-CLERK', 'Accounting Clerk', 8],
            ['MO', 'ADMIN-AIDE', 'Administrative Aide', 4],
        ];

        $positions = [];

        foreach ($definitions as [$departmentCode, $code, $title, $grade]) {
            $positions[$code] = Position::updateOrCreate(
                ['position_code' => $code],
                [
                    'department_id' => $departments[$departmentCode]->department_id,
                    'position_title' => $title,
                    'salary_grade' => $grade,
                    'status' => 'ACTIVE',
                ],
            );
        }

        return $positions;
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

        $employee = Employee::updateOrCreate(
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

        $record = EmploymentRecord::updateOrCreate(
            ['employee_id' => $employee->employee_id, 'position_id' => $position->position_id],
            [
                'department_id' => $position->department_id,
                'employment_type_id' => $employmentType->employment_type_id,
                'start_date' => '2024-01-01',
                'end_date' => null,
                'employment_status' => EmploymentRecord::STATUS_ACTIVE,
            ],
        );

        CompensationProfile::updateOrCreate(
            ['employment_id' => $record->employment_id, 'effective_from' => '2024-01-01'],
            [
                'grade_step_id' => null,
                'pay_basis' => $payBasis,
                'basic_rate' => $rate,
                'service_fraction' => $serviceFraction,
                'effective_to' => null,
            ],
        );
    }
}
