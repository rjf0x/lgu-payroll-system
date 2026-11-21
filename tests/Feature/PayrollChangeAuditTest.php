<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\EmployeeEarningAssignment;
use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\TaxBracket;
use App\Models\User;
use App\Services\PayrollAuditSnapshot;
use App\Services\PayrollComputationService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PayrollChangeAuditTest extends TestCase
{
    use RefreshDatabase;

    private function preparedDetail(): PayrollDetail
    {
        $this->seed([RolePermissionSeeder::class, DeductionTypeSeeder::class, StatutoryConfigSeeder::class]);
        ContributionRate::query()->update(['employee_rate' => 0, 'employer_rate' => 0]);
        TaxBracket::query()->update(['base_tax' => 0, 'marginal_tax_rate' => 0]);
        $officer = User::factory()->create();
        $officer->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($officer);
        $employment = EmploymentRecord::factory()->create();
        CompensationProfile::factory()->create(['employment_id' => $employment->getKey(), 'basic_rate' => 22000, 'effective_from' => '2024-01-01']);
        $run = PayrollRun::factory()->create(['created_by' => $officer->getKey()]);
        app(PayrollComputationService::class)->computeRun($run);

        return $run->details()->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function inputs(): array
    {
        return ['payable_days' => 22, 'input_reference' => 'QA payroll authority', 'inputs_confirmed' => 1, 'earnings' => [], 'deductions' => [], 'lbp_loan_deduction' => 0];
    }

    public function test_manual_line_replacement_preserves_original_and_new_identifiers_labels_amounts_and_totals(): void
    {
        $detail = $this->preparedDetail();
        $run = $detail->payrollRun;
        $earning = EarningType::factory()->create(['earning_name' => 'Approved allowance', 'is_taxable' => false]);
        $removed = EarningType::factory()->create(['earning_name' => 'Removed allowance', 'is_taxable' => false]);
        $deduction = DeductionType::factory()->create(['deduction_name' => 'Approved loan', 'is_statutory' => false, 'deduction_category' => 'OTHER']);
        $this->put(route('payroll-details.update', [$run, $detail]), [
            ...$this->inputs(), 'earnings' => [$earning->getKey() => 500, $removed->getKey() => 250], 'deductions' => [$deduction->getKey() => 100], 'lbp_loan_deduction' => 50,
        ])->assertRedirect();

        $this->put(route('payroll-details.update', [$run, $detail]), [
            ...$this->inputs(), 'earnings' => [$earning->getKey() => 900], 'deductions' => [$deduction->getKey() => 200], 'lbp_loan_deduction' => 75,
            'change_reason' => 'Revised payroll authority REF-702',
        ])->assertRedirect();

        $event = AuditLog::where('action', 'PAYROLL_DETAIL_UPDATED')->latest('audit_id')->firstOrFail();
        $this->assertSame(auth()->id(), $event->user_id);
        $this->assertSame('Revised payroll authority REF-702', $event->new_values['audit_context']['reason']);
        $oldEarnings = collect($event->old_values['earnings'])->keyBy('earning_type_id');
        $newEarnings = collect($event->new_values['earnings'])->keyBy('earning_type_id');
        $this->assertCount(2, $oldEarnings);
        $this->assertCount(1, $newEarnings);
        $this->assertSame('Approved allowance', $oldEarnings[$earning->getKey()]['earning_name_snapshot']);
        $this->assertSame(500.0, (float) $oldEarnings[$earning->getKey()]['amount']);
        $this->assertSame(250.0, (float) $oldEarnings[$removed->getKey()]['amount']);
        $this->assertSame(900.0, (float) $newEarnings[$earning->getKey()]['amount']);
        $oldLoan = collect($event->old_values['deductions'])->firstWhere('deduction_type_id', $deduction->getKey());
        $newLoan = collect($event->new_values['deductions'])->firstWhere('deduction_type_id', $deduction->getKey());
        $this->assertSame('Approved loan', $oldLoan['deduction_name_snapshot']);
        $this->assertSame(100.0, (float) $oldLoan['amount']);
        $this->assertSame(200.0, (float) $newLoan['amount']);
        $this->assertSame(50.0, (float) $event->old_values['lbp_loan_deduction']);
        $this->assertSame(75.0, (float) $event->new_values['lbp_loan_deduction']);
        $this->assertSame(22600.0, (float) $event->old_values['net_pay']);
        $this->assertSame(22625.0, (float) $event->new_values['net_pay']);
        $this->assertArrayHasKey('employerContributions', $event->new_values);
    }

    public function test_recomputation_retains_before_and_after_financial_lines_in_the_run_audit(): void
    {
        $detail = $this->preparedDetail();
        $earning = EarningType::factory()->create(['is_taxable' => false]);
        $assignment = EmployeeEarningAssignment::create([
            'employment_id' => $detail->employment_id, 'earning_type_id' => $earning->getKey(), 'amount' => 1000, 'frequency' => 'MONTHLY', 'effective_from' => '2024-01-01',
        ]);
        $run = $detail->payrollRun;
        $this->post(route('payroll-runs.compute', $run))->assertRedirect();
        $assignment->update(['amount' => 2000]);

        $this->post(route('payroll-runs.compute', $run), ['change_reason' => 'Apply corrected allowance order.'])->assertRedirect();

        $event = AuditLog::where('action', 'PAYROLL_RECALCULATED')->latest('audit_id')->firstOrFail();
        $this->assertSame(1000.0, (float) $event->old_values['details'][0]['earnings'][0]['amount']);
        $this->assertSame(2000.0, (float) $event->new_values['details'][0]['earnings'][0]['amount']);
        $this->assertSame('Apply corrected allowance order.', $event->new_values['audit_context']['reason']);
    }

    public function test_removing_a_detail_retains_its_financial_lines_after_the_rows_are_deleted(): void
    {
        $detail = $this->preparedDetail();
        $earning = EarningType::factory()->create(['is_taxable' => false]);
        $this->put(route('payroll-details.update', [$detail->payrollRun, $detail]), [...$this->inputs(), 'earnings' => [$earning->getKey() => 500]])->assertRedirect();

        $this->delete(route('payroll-details.destroy', [$detail->payrollRun, $detail]), ['reason' => 'Incorrect employee inclusion.'])->assertRedirect();

        $this->assertModelMissing($detail);
        $event = AuditLog::where('action', 'PAYROLL_DETAIL_REMOVED')->firstOrFail();
        $this->assertSame(500.0, (float) $event->old_values['detail']['earnings'][0]['amount']);
        $this->assertSame('Incorrect employee inclusion.', $event->new_values['reason']);
        $this->assertSame(0.0, (float) $event->new_values['totals']['net_pay']);
    }

    public function test_deleting_a_run_retains_its_financial_details_in_the_audit(): void
    {
        $detail = $this->preparedDetail();
        $earning = EarningType::factory()->create(['is_taxable' => false]);
        $this->put(route('payroll-details.update', [$detail->payrollRun, $detail]), [...$this->inputs(), 'earnings' => [$earning->getKey() => 500]])->assertRedirect();

        $this->delete(route('payroll-runs.destroy', $detail->payrollRun), ['change_reason' => 'Abandoned draft REF-705'])->assertRedirect();

        $this->assertModelMissing($detail);
        $event = AuditLog::where('action', 'PAYROLL_DELETED')->firstOrFail();
        $this->assertSame(500.0, (float) $event->old_values['details'][0]['earnings'][0]['amount']);
        $this->assertSame('Abandoned draft REF-705', $event->new_values['audit_context']['reason']);
    }

    public function test_failure_to_record_a_line_edit_rolls_back_lines_totals_and_nested_computation_events(): void
    {
        $detail = $this->preparedDetail();
        $earning = EarningType::factory()->create(['is_taxable' => false]);
        $before = app(PayrollAuditSnapshot::class)->detail($detail);
        $eventCount = AuditLog::count();
        AuditLog::creating(function (AuditLog $event): void {
            if ($event->action === 'PAYROLL_DETAIL_UPDATED') {
                throw new RuntimeException('Audit storage unavailable.');
            }
        });

        try {
            $this->put(route('payroll-details.update', [$detail->payrollRun, $detail]), [...$this->inputs(), 'earnings' => [$earning->getKey() => 500]])->assertServerError();

            $this->assertSame($before, app(PayrollAuditSnapshot::class)->detail($detail));
            $this->assertSame($eventCount, AuditLog::count());
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_register_generation_records_actor_run_and_reason_without_changing_payroll(): void
    {
        $detail = $this->preparedDetail();
        $before = app(PayrollAuditSnapshot::class)->detail($detail);

        $this->get(route('payroll-runs.register.export', $detail->payrollRun))->assertOk()->assertDownload();

        $event = AuditLog::where('action', 'REPORT_GENERATED')->firstOrFail();
        $this->assertSame(auth()->id(), $event->user_id);
        $this->assertSame('PAYROLL_REGISTER', $event->new_values['report_type']);
        $this->assertSame($detail->payroll_run_id, json_decode($event->new_values['filters'], true)['payroll_run_id']);
        $this->assertSame($before, app(PayrollAuditSnapshot::class)->detail($detail));
    }

    public function test_report_audit_failure_does_not_leave_a_successful_generation_record(): void
    {
        $detail = $this->preparedDetail();
        AuditLog::creating(function (AuditLog $event): void {
            if ($event->action === 'REPORT_GENERATED') {
                throw new RuntimeException('Audit storage unavailable.');
            }
        });

        try {
            $this->get(route('payroll-runs.register.export', $detail->payrollRun))->assertServerError();

            $this->assertDatabaseCount('generated_reports', 0);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'REPORT_GENERATED']);
        } finally {
            AuditLog::flushEventListeners();
        }
    }
}
