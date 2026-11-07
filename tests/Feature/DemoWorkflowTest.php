<?php

namespace Tests\Feature;

use App\Models\BlockchainAnchor;
use App\Models\BlockchainVerificationLog;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoPayrollWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_scenarios_are_repeatable_and_preserve_existing_work(): void
    {
        $this->seed([DatabaseSeeder::class, DemoPayrollWorkflowSeeder::class]);
        $runs = PayrollRun::orderBy('payroll_run_id')->get();
        $this->assertSame(['DRAFT', 'PENDING_APPROVAL', 'RETURNED', 'FINALIZED'], $runs->pluck('status')->all());
        $this->assertSame(8, $runs->last()->details()->count());
        $this->assertDatabaseHas('salary_grade_steps', ['grade_number' => 15, 'step_number' => 1, 'monthly_salary' => 45000]);

        $runs->first()->update(['status' => PayrollRun::STATUS_CANCELLED]);
        $this->travel(5)->minutes();
        $this->seed(DemoPayrollWorkflowSeeder::class);
        $this->assertSame($runs->pluck('updated_at')->map->toISOString()->all(), PayrollRun::orderBy('payroll_run_id')->get()->pluck('updated_at')->map->toISOString()->all());
        $this->assertSame(PayrollRun::STATUS_CANCELLED, $runs->first()->fresh()->status);
        $this->assertDatabaseCount('payroll_runs', 4);
    }

    public function test_demo_scenarios_cannot_be_seeded_in_production(): void
    {
        app()->instance('env', 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Demo payroll scenarios are restricted to local and testing environments.');

        app(DemoPayrollWorkflowSeeder::class)->run();
    }

    public function test_mock_defense_can_complete_the_http_workflow_with_separate_roles(): void
    {
        $this->seed([DatabaseSeeder::class, DemoPayrollWorkflowSeeder::class]);
        $officer = User::where('username', 'officer')->firstOrFail();
        $approver = User::where('username', 'approver')->firstOrFail();
        $viewer = User::where('username', 'viewer')->firstOrFail();
        $run = PayrollRun::where('status', PayrollRun::STATUS_DRAFT)->firstOrFail();

        $this->actingAs($viewer)->post(route('payroll-runs.compute', $run))->assertForbidden();
        $this->actingAs($officer)->post(route('payroll-runs.compute', $run))->assertRedirect();
        $this->assertSame(8, $run->details()->count());
        $this->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->post(route('payroll-runs.approve', $run))->assertForbidden();
        $this->actingAs($approver)->post(route('payroll-runs.approve', $run), ['remarks' => 'QA reviewed'])->assertRedirect();
        $this->post(route('payroll-runs.finalize', $run))->assertRedirect();
        $this->assertSame(PayrollRun::STATUS_FINALIZED, $run->fresh()->status);
        $this->actingAs($officer)->post(route('payroll-runs.compute', $run))->assertRedirect()->assertSessionHas('error');
        $this->get(route('payroll-runs.register', $run))->assertOk();
        $this->post(route('payroll-runs.payslips.generate', $run))->assertRedirect();
        $this->post(route('payroll-runs.payslips.generate', $run))->assertRedirect();
        $this->assertDatabaseCount('payslips', 8);
        $payslip = Payslip::firstOrFail();
        $this->post(route('payslips.publish', $payslip))->assertRedirect();
        $this->get(route('payslips.show', $payslip))->assertOk();
        $this->assertSame(Payslip::STATUS_PUBLISHED, $payslip->fresh()->publication_status);
        $this->actingAs($approver)->post(route('payroll-runs.anchor', $run))->assertRedirect();
        $anchor = BlockchainAnchor::firstOrFail();
        $this->post(route('blockchain-anchors.verify', $anchor))->assertRedirect();
        $this->assertDatabaseHas('blockchain_verification_logs', ['anchor_id' => $anchor->getKey(), 'verification_result' => BlockchainVerificationLog::RESULT_MATCH]);

        $run->details()->firstOrFail()->increment('net_pay');
        $this->post(route('blockchain-anchors.verify', $anchor))->assertRedirect();
        $this->assertDatabaseHas('blockchain_verification_logs', ['anchor_id' => $anchor->getKey(), 'verification_result' => BlockchainVerificationLog::RESULT_MISMATCH]);
    }
}
