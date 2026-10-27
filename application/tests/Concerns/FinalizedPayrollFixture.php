<?php

namespace Tests\Concerns;

use App\Enums\PersonnelClassification;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollFinalization;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollWorkflowService;
use Tests\Feature\StatutoryCalculatorTest;

trait FinalizedPayrollFixture
{
    private function actor(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function fixture(PersonnelClassification $category = PersonnelClassification::Permanent, string $period = '2026-10'): array
    {
        $this->actor($this->officer);
        $employee = Employee::factory()->create(['personnel_classification' => $category, 'employment_start_date' => '2020-01-01']);
        $schedule = SalarySchedule::where('personnel_classification', $category)->first();
        if (! $schedule) {
            $schedule = SalarySchedule::create(['name' => 'FICTIONAL ANCHOR SOURCE', 'personnel_classification' => $category, 'is_demonstration' => true, 'effective_from' => '2020-01-01', 'created_by' => $this->officer->id]);
            $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '30000.00']);
            $schedule->forceFill(['published_at' => now(), 'authority_reference' => 'FICTIONAL AUTHORITY'])->save();
        }
        $rate = $schedule->rates()->sole();
        EmployeeSalaryRecord::forceCreate([
            'employee_id' => $employee->id, 'salary_rate_id' => $rate->id, 'effective_from' => '2020-01-01',
            'change_type' => 'initial', 'personnel_classification' => $category->value, 'position' => 'Fictional Clerk',
            'monthly_salary' => '30000.00', 'is_demonstration' => true, 'step_basis_date' => '2020-01-01',
            'authority_reference' => 'FICTIONAL APPOINTMENT', 'reason' => 'Fictional anchoring fixture', 'created_by' => $this->officer->id,
        ]);
        $this->post('/payroll-batches', ['period' => $period, 'is_demonstration' => 1])->assertSessionHasNoErrors();
        $batch = PayrollBatch::orderByDesc('id')->firstOrFail();
        $this->post(route('payroll-employees.store', $batch), ['record_version' => $batch->record_version, 'employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $entry = $batch->entries()->sole();
        $this->put(route('payroll-inputs.update', [$batch, $entry]), [
            'record_version' => $batch->fresh()->record_version, 'loans' => [['label' => 'FICTIONAL LOAN', 'amount' => '0.00']],
            'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'authority_reference' => 'FICTIONAL INPUTS', 'change_reason' => 'Fictional anchoring inputs', 'authorization_confirmed' => 1,
        ])->assertSessionHasNoErrors();
        $this->post(route('payroll-calculations.store', $batch), ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::where('payroll_batch_id', $batch->id)->sole();
        $this->post(route('statutory-previews.store', [$batch, $run, $run->items()->sole()]), StatutoryCalculatorTest::data([
            'record_version' => $batch->fresh()->record_version,
        ]))->assertSessionHasNoErrors();
        $service = app(PayrollWorkflowService::class);
        foreach (['submit', 'approve', 'finalize'] as $action) {
            if ($action !== 'submit') {
                $this->actor($this->approver);
            }
            $this->post(route('payroll-reviews.transition', [$batch, $action]), [
                'record_version' => $batch->fresh()->record_version,
                'submission_id' => $service->latestSubmission($batch)?->id,
                'review_token' => $action === 'submit' ? $service->reviewToken($service->currentSnapshot($batch->fresh())) : null,
                'reason' => 'FICTIONAL anchoring '.$action, 'review_confirmed' => 1,
            ])->assertSessionHasNoErrors();
        }

        return [$batch->fresh(), PayrollFinalization::where('payroll_batch_id', $batch->id)->sole()];
    }
}
