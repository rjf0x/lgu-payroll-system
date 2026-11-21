<?php

namespace Tests\Feature;

use App\Jobs\GeneratePayslips;
use App\Models\AuditLog;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Role;
use App\Models\User;
use App\Services\PayslipGenerationService;
use App\Services\PayslipPublicationService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoPayrollWorkflowSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayslipLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role = Role::PAYROLL_OFFICER): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $role)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    private function payslip(string $runStatus = PayrollRun::STATUS_APPROVED, string $status = Payslip::STATUS_GENERATED): Payslip
    {
        $run = PayrollRun::factory()->create(['status' => $runStatus]);
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey()]);

        return $detail->payslip()->create([
            'payslip_number' => 'PS-LIFECYCLE-'.$detail->getKey(),
            'generated_at' => now()->subDay(),
            'published_at' => $status === Payslip::STATUS_PUBLISHED ? now()->subHour() : null,
            'publication_status' => $status,
        ]);
    }

    public function test_approved_payslips_are_printable_previews_and_cannot_be_published(): void
    {
        $officer = $this->userWithRole();
        $payslip = $this->payslip();

        $this->actingAs($officer)->get(route('payslips.show', $payslip))
            ->assertOk()->assertSee('PREVIEW')->assertSee('45,000.00')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('payroll-runs.payslips', $payslip->payrollDetail->payrollRun))
            ->assertOk()->assertSee('PREVIEW')->assertDontSee(route('payslips.publish', $payslip));
        $this->postJson(route('payslips.publish', $payslip))->assertUnprocessable()
            ->assertJsonPath('message', 'Finalize this payroll run before publishing payslips.');

        $this->assertDatabaseHas('payslips', ['payslip_id' => $payslip->getKey(), 'publication_status' => 'GENERATED', 'published_at' => null]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_PUBLISHED']);
    }

    /** @return array<string, array{string}> */
    public static function unapprovedRunStates(): array
    {
        return [
            'draft' => [PayrollRun::STATUS_DRAFT],
            'processing' => [PayrollRun::STATUS_PROCESSING],
            'pending' => [PayrollRun::STATUS_PENDING_APPROVAL],
            'returned' => [PayrollRun::STATUS_RETURNED],
            'rejected' => [PayrollRun::STATUS_REJECTED],
            'cancelled' => [PayrollRun::STATUS_CANCELLED],
        ];
    }

    #[DataProvider('unapprovedRunStates')]
    public function test_old_published_payslips_cannot_be_viewed_or_republished_without_current_approval(string $status): void
    {
        $officer = $this->userWithRole();
        $payslip = $this->payslip($status, Payslip::STATUS_PUBLISHED);
        $original = $payslip->fresh()->getAttributes();

        $this->actingAs($officer)->getJson(route('payslips.show', $payslip))->assertUnprocessable()
            ->assertJsonPath('message', 'This payslip is withdrawn or its payroll is awaiting approval. Generate a current payslip after approval.');
        $this->postJson(route('payslips.publish', $payslip))->assertUnprocessable();
        $this->get(route('payroll-runs.payslips', $payslip->payrollDetail->payrollRun))
            ->assertOk()->assertSee('WITHDRAWN')->assertDontSee(route('payslips.show', $payslip))
            ->assertDontSee(route('payslips.publish', $payslip));

        $this->assertSame($original, $payslip->fresh()->getAttributes());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_PUBLISHED']);
    }

    public function test_return_withdraws_previews_and_legacy_published_payslips_with_audit_evidence(): void
    {
        $approver = $this->userWithRole(Role::APPROVER);
        $payslip = $this->payslip(PayrollRun::STATUS_APPROVED, Payslip::STATUS_PUBLISHED);
        $run = $payslip->payrollDetail->payrollRun;
        $other = PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'employee_number_snapshot' => 'TEST-002']);
        $preview = $other->payslip()->create(['payslip_number' => 'PS-PREVIEW', 'generated_at' => now(), 'publication_status' => Payslip::STATUS_GENERATED]);
        $unrelated = $this->payslip(PayrollRun::STATUS_FINALIZED, Payslip::STATUS_PUBLISHED);
        $originalPublishedAt = $payslip->published_at->toDateTimeString();

        $this->actingAs($approver)->post(route('payroll-runs.return', $run), ['remarks' => 'Correct loan deduction.'])->assertRedirect();

        $this->assertSame('RETURNED', $run->fresh()->status);
        $this->assertSame('WITHDRAWN', $payslip->fresh()->publication_status);
        $this->assertSame('WITHDRAWN', $preview->fresh()->publication_status);
        $this->assertSame('PUBLISHED', $unrelated->fresh()->publication_status);
        $event = AuditLog::where('action', 'PAYSLIP_WITHDRAWN')->where('record_id', (string) $payslip->getKey())->firstOrFail();
        $this->assertSame($approver->getKey(), $event->user_id);
        $this->assertSame('PUBLISHED', $event->old_values['publication_status']);
        $this->assertSame($originalPublishedAt, $event->old_values['published_at']);
        $this->assertSame('Correct loan deduction.', $event->new_values['reason']);
        $this->getJson(route('payslips.show', $payslip))->assertUnprocessable();
    }

    public function test_a_preparer_cannot_return_payroll_or_withdraw_its_payslips(): void
    {
        $preparer = $this->userWithRole(Role::APPROVER);
        $payslip = $this->payslip();
        $run = $payslip->payrollDetail->payrollRun;
        $run->update(['created_by' => $preparer->getKey()]);

        $this->actingAs($preparer)->postJson(route('payroll-runs.return', $run), ['remarks' => 'Change payroll.'])->assertForbidden();

        $this->assertSame('APPROVED', $run->fresh()->status);
        $this->assertSame('GENERATED', $payslip->fresh()->publication_status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_WITHDRAWN']);
    }

    public function test_withdrawn_payslips_require_regeneration_even_after_finalization(): void
    {
        $officer = $this->userWithRole();
        $payslip = $this->payslip(PayrollRun::STATUS_FINALIZED, Payslip::STATUS_WITHDRAWN);

        $this->actingAs($officer)->getJson(route('payslips.show', $payslip))->assertUnprocessable();
        $this->postJson(route('payslips.publish', $payslip))->assertUnprocessable()
            ->assertJsonPath('message', 'Generate a current payslip before publishing it.');

        $this->assertSame('WITHDRAWN', $payslip->fresh()->publication_status);
    }

    public function test_regeneration_clears_previous_publication_and_file_reference_and_records_the_requester(): void
    {
        $this->freezeTime();
        $officer = $this->userWithRole();
        $payslip = $this->payslip(PayrollRun::STATUS_APPROVED, Payslip::STATUS_PUBLISHED);
        $payslip->update(['publication_status' => Payslip::STATUS_WITHDRAWN, 'file_path' => 'private/obsolete.pdf']);
        $run = $payslip->payrollDetail->payrollRun;

        $this->actingAs($officer)->post(route('payroll-runs.payslips.generate', $run))->assertRedirect()->assertSessionHas('status', 'Generated 1 payslip(s).');

        $this->assertDatabaseCount('payslips', 1);
        $this->assertDatabaseHas('payslips', ['payslip_id' => $payslip->getKey(), 'publication_status' => 'GENERATED', 'published_at' => null, 'file_path' => null, 'generated_at' => now()->toDateTimeString()]);
        $event = AuditLog::where('action', 'PAYSLIP_GENERATED')->firstOrFail();
        $this->assertSame($officer->getKey(), $event->user_id);
        $this->assertSame('WITHDRAWN', $event->old_values['publication_status']);
        $this->assertSame('GENERATED', $event->new_values['publication_status']);
        $this->get(route('payslips.show', $payslip))->assertOk()->assertSee('PREVIEW');
    }

    public function test_publication_and_regeneration_are_idempotent_for_a_finalized_payslip(): void
    {
        $this->freezeTime();
        $officer = $this->userWithRole();
        $payslip = $this->payslip(PayrollRun::STATUS_FINALIZED);
        $run = $payslip->payrollDetail->payrollRun;

        $this->actingAs($officer)->post(route('payslips.publish', $payslip))->assertRedirect();
        $publishedAt = $payslip->fresh()->published_at->toDateTimeString();
        $this->travel(5)->minutes();
        $this->post(route('payslips.publish', $payslip))->assertRedirect();
        $this->post(route('payroll-runs.payslips.generate', $run))->assertRedirect()->assertSessionHas('status', 'Generated 0 payslip(s).');

        $this->assertSame($publishedAt, $payslip->fresh()->published_at->toDateTimeString());
        $this->assertSame(1, AuditLog::where('action', 'PAYSLIP_PUBLISHED')->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_GENERATED']);
        $this->get(route('payslips.show', $payslip))->assertOk()->assertSee('PUBLISHED')->assertDontSee('PREVIEW');
    }

    public function test_printing_an_old_published_approved_payslip_identifies_it_as_a_preview(): void
    {
        $officer = $this->userWithRole();
        $payslip = $this->payslip(PayrollRun::STATUS_APPROVED, Payslip::STATUS_PUBLISHED);

        $this->actingAs($officer)->get(route('payslips.show', $payslip))->assertOk()->assertSee('PREVIEW')->assertDontSee('PUBLISHED');
    }

    public function test_a_stale_loaded_run_cannot_bypass_publication_validation(): void
    {
        $officer = $this->userWithRole();
        $payslip = $this->payslip(PayrollRun::STATUS_FINALIZED);
        $payslip->load('payrollDetail.payrollRun');
        PayrollRun::whereKey($payslip->payrollDetail->payroll_run_id)->update(['status' => PayrollRun::STATUS_RETURNED]);

        try {
            app(PayslipPublicationService::class)->publish($payslip, $officer->getKey());
            $this->fail('A stale finalized model bypassed the current run state.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('GENERATED', $payslip->fresh()->publication_status);
        }
    }

    public function test_a_queued_request_cannot_regenerate_withdrawn_payslips_during_correction(): void
    {
        $officer = $this->userWithRole();
        $payslip = $this->payslip(PayrollRun::STATUS_RETURNED, Payslip::STATUS_WITHDRAWN);

        try {
            (new GeneratePayslips($payslip->payrollDetail->payroll_run_id, $officer->getKey()))->handle(app(PayslipGenerationService::class));
            $this->fail('A queued request regenerated a withdrawn payslip during correction.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('WITHDRAWN', $payslip->fresh()->publication_status);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_GENERATED']);
        }
    }

    public function test_queued_regeneration_records_the_requester_without_publishing(): void
    {
        $officer = $this->userWithRole();
        $payslip = $this->payslip(PayrollRun::STATUS_FINALIZED, Payslip::STATUS_WITHDRAWN);

        (new GeneratePayslips($payslip->payrollDetail->payroll_run_id, $officer->getKey()))->handle(app(PayslipGenerationService::class));

        $this->assertSame('GENERATED', $payslip->fresh()->publication_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYSLIP_GENERATED', 'user_id' => $officer->getKey(), 'record_id' => (string) $payslip->getKey()]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_PUBLISHED']);
    }

    public function test_unauthenticated_and_unprivileged_requests_cannot_publish_or_read_payslips(): void
    {
        $payslip = $this->payslip(PayrollRun::STATUS_FINALIZED);

        $this->get(route('payslips.show', $payslip))->assertRedirect(route('login'));
        $this->post(route('payslips.publish', $payslip))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('payslips.show', $payslip))->assertForbidden();
        $this->post(route('payslips.publish', $payslip))->assertForbidden();

        $this->assertSame('GENERATED', $payslip->fresh()->publication_status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_PUBLISHED']);
    }

    public function test_a_failed_withdrawal_audit_rolls_back_the_return_and_all_payslip_changes(): void
    {
        $approver = $this->userWithRole(Role::APPROVER);
        $payslip = $this->payslip();
        $run = $payslip->payrollDetail->payrollRun;
        AuditLog::creating(function (AuditLog $log): void {
            if ($log->action === 'PAYSLIP_WITHDRAWN') {
                throw new RuntimeException('Audit storage unavailable.');
            }
        });

        try {
            $this->actingAs($approver)->post(route('payroll-runs.return', $run), ['remarks' => 'Correct payroll.'])->assertServerError();

            $this->assertSame('APPROVED', $run->fresh()->status);
            $this->assertSame('GENERATED', $payslip->fresh()->publication_status);
            $this->assertDatabaseMissing('payroll_approvals', ['payroll_run_id' => $run->getKey(), 'action' => 'RETURNED']);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_a_failed_publication_audit_rolls_back_publication(): void
    {
        $officer = $this->userWithRole();
        $payslip = $this->payslip(PayrollRun::STATUS_FINALIZED);
        AuditLog::creating(function (AuditLog $log): void {
            if ($log->action === 'PAYSLIP_PUBLISHED') {
                throw new RuntimeException('Audit storage unavailable.');
            }
        });

        try {
            $this->actingAs($officer)->post(route('payslips.publish', $payslip))->assertServerError();

            $this->assertDatabaseHas('payslips', ['payslip_id' => $payslip->getKey(), 'publication_status' => 'GENERATED', 'published_at' => null]);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_a_failed_generation_audit_preserves_the_withdrawn_payslip(): void
    {
        $officer = $this->userWithRole();
        $payslip = $this->payslip(PayrollRun::STATUS_APPROVED, Payslip::STATUS_WITHDRAWN);
        $original = $payslip->fresh()->getAttributes();
        AuditLog::creating(function (AuditLog $log): void {
            if ($log->action === 'PAYSLIP_GENERATED') {
                throw new RuntimeException('Audit storage unavailable.');
            }
        });

        try {
            $this->actingAs($officer)->post(route('payroll-runs.payslips.generate', $payslip->payrollDetail->payrollRun))->assertServerError();

            $this->assertSame($original, $payslip->fresh()->getAttributes());
            $this->assertDatabaseCount('payslips', 1);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_rejection_withdraws_existing_outputs_before_the_run_becomes_editable(): void
    {
        $approver = $this->userWithRole(Role::APPROVER);
        $payslip = $this->payslip(PayrollRun::STATUS_PENDING_APPROVAL, Payslip::STATUS_PUBLISHED);

        $this->actingAs($approver)->post(route('payroll-runs.reject', $payslip->payrollDetail->payrollRun), ['remarks' => 'Incorrect source amounts.'])->assertRedirect();

        $this->assertSame('REJECTED', $payslip->payrollDetail->payrollRun->fresh()->status);
        $this->assertSame('WITHDRAWN', $payslip->fresh()->publication_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYSLIP_WITHDRAWN', 'record_id' => (string) $payslip->getKey()]);
    }

    public function test_finalization_withdraws_legacy_early_publications_without_discarding_current_previews(): void
    {
        $approver = $this->userWithRole(Role::APPROVER);
        $payslip = $this->payslip(PayrollRun::STATUS_APPROVED, Payslip::STATUS_PUBLISHED);
        $run = $payslip->payrollDetail->payrollRun;
        $other = PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'employee_number_snapshot' => 'TEST-002']);
        $preview = $other->payslip()->create(['payslip_number' => 'PS-PREVIEW', 'generated_at' => now(), 'publication_status' => Payslip::STATUS_GENERATED]);

        $this->actingAs($approver)->post(route('payroll-runs.finalize', $run))->assertRedirect();

        $this->assertSame('FINALIZED', $run->fresh()->status);
        $this->assertSame('WITHDRAWN', $payslip->fresh()->publication_status);
        $this->assertSame('GENERATED', $preview->fresh()->publication_status);
        $this->postJson(route('payslips.publish', $payslip))->assertUnprocessable();
        $this->post(route('payslips.publish', $preview))->assertRedirect();
        $this->assertSame('PUBLISHED', $preview->fresh()->publication_status);
    }

    public function test_finalized_payslips_cannot_be_returned_for_editing(): void
    {
        $approver = $this->userWithRole(Role::APPROVER);
        $payslip = $this->payslip(PayrollRun::STATUS_FINALIZED, Payslip::STATUS_PUBLISHED);

        $this->actingAs($approver)->postJson(route('payroll-runs.return', $payslip->payrollDetail->payrollRun), ['remarks' => 'Change finalized payroll.'])->assertUnprocessable();

        $this->assertSame('FINALIZED', $payslip->payrollDetail->payrollRun->fresh()->status);
        $this->assertSame('PUBLISHED', $payslip->fresh()->publication_status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_WITHDRAWN']);
    }

    public function test_correction_requires_fresh_approval_generation_finalization_and_publication(): void
    {
        $this->freezeTime();
        $this->seed([DatabaseSeeder::class, DemoPayrollWorkflowSeeder::class]);
        $officer = User::where('username', 'officer')->firstOrFail();
        $approver = User::where('username', 'approver')->firstOrFail();
        $run = PayrollRun::where('status', PayrollRun::STATUS_DRAFT)->firstOrFail();
        $this->actingAs($officer)->post(route('payroll-runs.compute', $run))->assertRedirect();
        $inputs = ['payable_days' => 22, 'input_reference' => 'Reviewed fictional payroll', 'inputs_confirmed' => 1, 'earnings' => [], 'deductions' => [], 'lbp_loan_deduction' => 0];
        foreach ($run->details as $detail) {
            $this->put(route('payroll-details.update', [$run, $detail]), $inputs)->assertRedirect();
        }
        $this->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->actingAs($approver)->post(route('payroll-runs.approve', $run))->assertRedirect();
        $this->actingAs($officer)->post(route('payroll-runs.payslips.generate', $run))->assertRedirect();
        $detail = $run->details()->firstOrFail();
        $payslip = $detail->payslip;
        $originalGeneratedAt = $payslip->generated_at->toDateTimeString();
        $this->actingAs($approver)->post(route('payroll-runs.return', $run), ['remarks' => 'Correct LBP deduction.'])->assertRedirect();
        $this->travel(5)->minutes();

        $this->actingAs($officer)->put(route('payroll-details.update', [$run, $detail]), [...$inputs, 'lbp_loan_deduction' => 1234.56])->assertRedirect();

        $this->assertSame('1234.56', $detail->fresh()->lbp_loan_deduction);
        $this->getJson(route('payslips.show', $payslip))->assertUnprocessable();
        $this->postJson(route('payslips.publish', $payslip))->assertUnprocessable();
        $this->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->actingAs($approver)->post(route('payroll-runs.approve', $run))->assertRedirect();
        $this->getJson(route('payslips.show', $payslip))->assertUnprocessable();
        $this->actingAs($officer)->post(route('payroll-runs.payslips.generate', $run))->assertRedirect();
        $this->assertNotSame($originalGeneratedAt, $payslip->fresh()->generated_at->toDateTimeString());
        $this->get(route('payslips.show', $payslip))->assertOk()->assertSee('PREVIEW')->assertSee('1,234.56');
        $this->postJson(route('payslips.publish', $payslip))->assertUnprocessable();
        $this->actingAs($approver)->post(route('payroll-runs.finalize', $run))->assertRedirect();
        $this->actingAs($officer)->post(route('payslips.publish', $payslip))->assertRedirect();

        $this->get(route('payslips.show', $payslip))->assertOk()->assertSee('1,234.56')->assertSee('PUBLISHED')->assertDontSee('PREVIEW');
        $this->assertSame(2, $run->approvals()->where('action', 'APPROVED')->count());
        $this->assertSame('PUBLISHED', $payslip->fresh()->publication_status);
        $this->assertSame(2, AuditLog::where('action', 'PAYSLIP_GENERATED')->where('record_id', (string) $payslip->getKey())->count());
    }
}
