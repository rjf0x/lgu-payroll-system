<?php

namespace Tests\Feature;

use App\Jobs\GeneratePayslips;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Services\PayslipGenerationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QueuedPayslipTest extends TestCase
{
    use DatabaseMigrations;

    private function officer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    public function test_payslip_requests_can_be_dispatched_to_the_reports_queue(): void
    {
        $officer = $this->officer();
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        config(['deployment.queue_payslips' => true]);
        Queue::fake([GeneratePayslips::class]);

        $this->actingAs($officer)->post(route('payroll-runs.payslips.generate', $run))->assertRedirect()
            ->assertSessionHas('status', 'Payslip generation queued. Refresh this page after the report worker finishes.');

        Queue::assertPushedOn('reports', GeneratePayslips::class, fn ($job) => $job->payrollRunId === $run->getKey() && $job->requestedBy === $officer->getKey());
        $this->assertDatabaseCount('payslips', 0);
    }

    public function test_processing_a_report_twice_does_not_duplicate_payslips(): void
    {
        $officer = $this->officer();
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey()]);
        $job = new GeneratePayslips($run->getKey(), $officer->getKey());

        $job->handle(app(PayslipGenerationService::class));
        $job->handle(app(PayslipGenerationService::class));

        $this->assertDatabaseCount('payslips', 1);
        $this->assertDatabaseHas('payslips', ['publication_status' => 'GENERATED']);
    }

    public function test_deactivated_requester_cannot_generate_queued_payslips(): void
    {
        $officer = $this->officer();
        $officer->update(['account_status' => User::STATUS_INACTIVE]);
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);

        try {
            (new GeneratePayslips($run->getKey(), $officer->getKey()))->handle(app(PayslipGenerationService::class));
            $this->fail('Inactive requester generated a report.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertDatabaseCount('payslips', 0);
        }
    }

    public function test_worker_rechecks_run_state_before_generating_payslips(): void
    {
        $officer = $this->officer();
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_DRAFT]);

        try {
            (new GeneratePayslips($run->getKey(), $officer->getKey()))->handle(app(PayslipGenerationService::class));
            $this->fail('Draft payslips were generated.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertDatabaseCount('payslips', 0);
        }
    }

    public function test_database_worker_processes_an_authorized_payslip_request(): void
    {
        $officer = $this->officer();
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey()]);
        config(['deployment.queue_payslips' => true, 'queue.default' => 'database']);

        $this->actingAs($officer)->post(route('payroll-runs.payslips.generate', $run))->assertRedirect();
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'reports', '--once' => true, '--tries' => 1])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('payslips', 1);
    }
}
