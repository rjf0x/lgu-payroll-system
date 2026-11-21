<?php

namespace App\Services;

use App\Models\PayrollRun;
use App\Models\Payslip;
use Illuminate\Support\Facades\DB;

class PayslipPublicationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function publish(Payslip $payslip, int $userId): void
    {
        DB::transaction(function () use ($payslip, $userId): void {
            $runId = $payslip->payrollDetail()->value('payroll_run_id');
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($runId);
            $payslip = Payslip::query()->lockForUpdate()->findOrFail($payslip->getKey());

            abort_unless($run->status === PayrollRun::STATUS_FINALIZED, 422, 'Finalize this payroll run before publishing payslips.');
            if ($payslip->publication_status === Payslip::STATUS_PUBLISHED) {
                return;
            }
            abort_unless($payslip->isPublishableFor($run), 422, 'Generate a current payslip before publishing it.');

            $oldValues = $payslip->getAttributes();
            $payslip->update(['publication_status' => Payslip::STATUS_PUBLISHED, 'published_at' => now()]);
            $this->auditLogger->log('PAYSLIP_PUBLISHED', 'Payroll', $userId, 'payslips', (string) $payslip->getKey(), $oldValues, $payslip->getAttributes());
        });
    }

    /**
     * Called within the approval transition transaction, with the run already locked.
     */
    public function withdraw(PayrollRun $run, int $userId, ?string $reason, bool $publishedOnly = false): void
    {
        $payslips = Payslip::query()
            ->whereHas('payrollDetail', fn ($query) => $query->where('payroll_run_id', $run->getKey()))
            ->whereIn('publication_status', $publishedOnly ? [Payslip::STATUS_PUBLISHED] : [Payslip::STATUS_GENERATED, Payslip::STATUS_PUBLISHED])
            ->lockForUpdate()->get();

        foreach ($payslips as $payslip) {
            $oldValues = $payslip->getAttributes();
            $payslip->update(['publication_status' => Payslip::STATUS_WITHDRAWN]);
            $this->auditLogger->log('PAYSLIP_WITHDRAWN', 'Payroll', $userId, 'payslips', (string) $payslip->getKey(), $oldValues, [
                ...$payslip->getAttributes(), 'payroll_run_id' => $run->getKey(), 'reason' => $reason,
            ]);
        }
    }
}
