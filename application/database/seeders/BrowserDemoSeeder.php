<?php

namespace Database\Seeders;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\PayrollInputService;
use App\Services\PayrollPreparationService;
use App\Services\PayrollRuleVersionService;
use App\Services\SalaryService;
use App\Services\StatutoryPreviewService;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit local fixture; never invoked by DatabaseSeeder. */
class BrowserDemoSeeder extends Seeder
{
    public const PASSWORD = 'LocalDemo2026!';

    public const PERIOD = '2026-11';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('BrowserDemoSeeder is restricted to local/testing environments.');
        }

        $batch = DB::transaction(function () {
            $admin = $this->account('demo.admin', 'Demo Administrator', Role::Administrator);
            $officer = $this->account('demo.payroll', 'Demo Payroll Officer', Role::PayrollOfficer);
            $this->account('demo.approver', 'Demo Approver', Role::Approver);
            $this->account('demo.viewer', 'Demo Viewer', Role::Viewer);
            $adminRequest = $this->request($admin);
            $officerRequest = $this->request($officer);
            $salary = app(SalaryService::class);
            $employeeIds = [];

            foreach ([
                ['DEMO-P001', 'Ana', 'Santos', PersonnelClassification::Permanent, 'Accounting Clerk'],
                ['DEMO-C001', 'Ben', 'Reyes', PersonnelClassification::Coterminous, 'Budget Assistant'],
                ['DEMO-T001', 'Carla', 'Cruz', PersonnelClassification::PartTime, 'Records Aide'],
            ] as [$number, $first, $last, $category, $position]) {
                $legacy = SalarySchedule::whereNull('coverage_key')->where('personnel_classification', $category)->whereDate('effective_from', self::PERIOD.'-01')->where('is_demonstration', true)->first();
                $name = $legacy ? 'FICTIONAL November '.$category->label() : 'FICTIONAL November All Departments';
                $schedule = $legacy ?? SalarySchedule::where('coverage_key', SalarySchedule::coverageKey(null))->whereDate('effective_from', self::PERIOD.'-01')->where('is_demonstration', true)->first();
                if (! $schedule) {
                    $schedule = $salary->saveSchedule($adminRequest, [
                        'name' => $name, 'department_scope' => 'all',
                        'effective_from' => self::PERIOD.'-01', 'is_demonstration' => true,
                    ]);
                    $salary->saveRate($adminRequest, $schedule, ['grade' => 1, 'step' => 1, 'monthly_salary' => '30000.00']);
                    $salary->publish($adminRequest, $schedule, 'FICTIONAL-SALARY-001');
                    $schedule->refresh();
                }
                $this->requireMatch($schedule->name === $name && $schedule->created_by === $admin->id
                    && $schedule->published_at !== null && $schedule->authority_reference === 'FICTIONAL-SALARY-001', 'salary schedule '.$category->value);
                $rate = $schedule->rates()->where('grade', 1)->where('step', 1)->first();
                $this->requireMatch($rate && $rate->monthly_salary === '30000.00', 'salary rate '.$category->value);

                $attributes = [
                    'employee_number' => $number, 'first_name' => $first, 'last_name' => $last,
                    'middle_name' => null, 'suffix' => null, 'position' => $position,
                    'office' => 'Fictional Accounting Office', 'personnel_classification' => $category->value,
                    'employment_start_date' => '2024-01-01', 'is_active' => true,
                ];
                $employee = Employee::firstOrCreate(['employee_number' => $number], $attributes);
                foreach ($attributes as $field => $value) {
                    $actual = $field === 'employment_start_date' ? $employee->employment_start_date->format('Y-m-d')
                        : ($field === 'personnel_classification' ? $employee->personnel_classification->value : $employee->$field);
                    $this->requireMatch($actual === $value, 'employee '.$number);
                }
                $record = EmployeeSalaryRecord::where('employee_id', $employee->id)->whereDate('effective_from', self::PERIOD.'-01')->first();
                if (! $record) {
                    $record = $salary->recordSalary($officerRequest, $employee, [
                        'salary_rate_id' => $rate->id, 'effective_from' => self::PERIOD.'-01',
                        'change_type' => 'initial', 'position' => $position, 'step_basis_date' => '2024-01-01',
                        'authority_reference' => 'FICTIONAL-APPOINTMENT-001',
                        'reason' => 'Initial fictional browser test assignment',
                    ]);
                }
                $this->requireMatch($record->salary_rate_id === $rate->id && $record->created_by === $officer->id
                    && $record->authority_reference === 'FICTIONAL-APPOINTMENT-001' && $record->is_demonstration
                    && $record->monthly_salary === '30000.00' && $record->position === $position
                    && $record->personnel_classification === $category && $record->change_type === 'initial'
                    && $record->step_basis_date->format('Y-m-d') === '2024-01-01', 'salary assignment '.$number);
                $employeeIds[] = $employee->id;
            }

            $preparation = app(PayrollPreparationService::class);
            $batch = PayrollBatch::whereDate('period_start', self::PERIOD.'-01')->where('is_demonstration', true)->where('payroll_version', 1)->first();
            if ($batch) {
                $this->requireMatch($batch->created_by === $officer->id, 'November demonstration payroll');

                return $batch; // Never reset inputs, workflow, documents or later correction versions.
            }
            $batch = $preparation->create($officerRequest, ['period' => self::PERIOD, 'is_demonstration' => true]);
            $preparation->add($officerRequest, $batch, ['record_version' => $batch->record_version, 'employee_ids' => $employeeIds]);
            foreach ($batch->entries as $entry) {
                app(PayrollInputService::class)->save($officerRequest, $batch, $entry, [
                    'record_version' => $batch->fresh()->record_version,
                    'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
                    'authority_reference' => 'FICTIONAL-INPUTS-001', 'change_reason' => 'Zero-adjustment browser test',
                ]);
            }
            $run = app(PayrollCalculationService::class)->calculate($officerRequest, $batch, $batch->fresh()->record_version);
            foreach ($run->items as $item) {
                $profile = app(PayrollRuleVersionService::class)->profile($item->entry_snapshot['personnel_classification'], self::PERIOD.'-01', true);
                app(StatutoryPreviewService::class)->save($officerRequest, $batch, $run, $item, [
                    'record_version' => $batch->fresh()->record_version, 'base_revision' => 0,
                    'configuration_version_id' => $profile['_configuration']['version_id'] ?? null,
                    'gsis_coverage' => 'applicable', 'gsis_basis' => '30000.00',
                    'pagibig_coverage' => 'applicable', 'pagibig_basis' => '30000.00',
                    'philhealth_coverage' => 'applicable', 'philhealth_basis' => '30000.00',
                    'tax_method' => 'monthly_regular', 'taxable_regular' => '26350.00',
                    'taxable_other' => '0.00', 'qualifying_bonuses' => '0.00', 'prior_withheld' => '0.00',
                    'elapsed_periods' => null, 'reference' => 'FICTIONAL-STATUTORY-001',
                    'reason' => 'Reviewed fictional browser scenario', 'demonstration_confirmed' => 1,
                ]);
            }

            return $batch->fresh();
        });

        $this->command?->info('Browser demo ready: November 2026 payroll #'.$batch->id.' ('.$batch->status.'). Existing records/passwords were not reset.');
        $this->command?->info('Accounts: demo.admin, demo.payroll, demo.approver, demo.viewer. New-account password: '.self::PASSWORD);
        $this->command?->info('Sign in as demo.payroll and review the November demonstration payroll. Approval/finalization remain manual.');
    }

    private function account(string $username, string $name, Role $role): User
    {
        $user = User::where('username', $username)->first();
        if (! $user) {
            $user = User::forceCreate(['username' => $username, 'name' => $name, 'role' => $role, 'is_active' => true, 'password' => self::PASSWORD]);
        }
        $this->requireMatch($user->name === $name && $user->role === $role && $user->is_active, 'account '.$username);

        return $user;
    }

    private function request(User $user): Request
    {
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $user);
        $session = new Store('browser-demo-seed', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', $user->auth_version);
        $request->setLaravelSession($session);

        return $request;
    }

    private function requireMatch(bool $matches, string $label): void
    {
        if (! $matches) {
            throw new RuntimeException('Browser demo conflicts with existing '.$label.'. Nothing was overwritten; the seed transaction was rolled back.');
        }
    }
}
