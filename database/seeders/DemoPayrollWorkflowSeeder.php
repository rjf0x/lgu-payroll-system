<?php

namespace Database\Seeders;

use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollApprovalService;
use App\Services\PayrollComputationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoPayrollWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo payroll scenarios are restricted to local and testing environments.');
        }

        $officer = User::where('username', 'officer')->firstOrFail();
        $approver = User::where('username', 'approver')->firstOrFail();

        DB::transaction(function () use ($officer, $approver): void {
            foreach ([1 => 'Ready to prepare', 2 => 'Pending review', 3 => 'Returned for correction', 4 => 'Finalized proof'] as $month => $scenario) {
                $date = now()->setDate(2026, $month, 1)->startOfDay();
                $period = PayrollPeriod::firstOrCreate(
                    ['period_name' => 'DEMO QA - '.$scenario],
                    [
                        'payroll_type' => PayrollPeriod::TYPE_MONTHLY,
                        'start_date' => $date->toDateString(),
                        'end_date' => $date->copy()->endOfMonth()->toDateString(),
                        'pay_date' => $date->copy()->endOfMonth()->toDateString(),
                        'status' => 'OPEN',
                    ],
                );
                $run = PayrollRun::firstOrCreate(
                    ['payroll_period_id' => $period->getKey(), 'run_number' => 1],
                    ['run_type' => 'REGULAR', 'status' => PayrollRun::STATUS_DRAFT, 'created_by' => $officer->getKey()],
                );

                if (! $run->wasRecentlyCreated || $month === 1) {
                    continue;
                }

                app(PayrollComputationService::class)->computeRun($run);
                foreach ($run->details as $detail) {
                    $detail->update(['payable_days' => 22, 'input_reference' => 'Fictional approved demo summary', 'input_recorded_by' => $officer->getKey(), 'input_confirmed_at' => now()]);
                }
                app(PayrollComputationService::class)->computeRun($run);
                $workflow = app(PayrollApprovalService::class);
                $workflow->submit($run, $officer);

                if ($month === 3) {
                    $workflow->returnForCorrection($run, $approver, 'Demo: confirm approved payroll inputs before resubmission.');
                } elseif ($month === 4) {
                    $workflow->approve($run, $approver, 'Demo: reviewed fictional payroll.');
                    $workflow->finalize($run, $approver);
                }
            }
        });
    }
}
