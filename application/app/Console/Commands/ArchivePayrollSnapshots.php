<?php

namespace App\Console\Commands;

use App\Models\PayrollFinalization;
use App\Services\PayrollSnapshotService;
use Illuminate\Console\Command;
use Throwable;

class ArchivePayrollSnapshots extends Command
{
    protected $signature = 'payroll:archive-snapshots {--apply : Persist bytes after validating each finalization} {--finalization= : Inspect one finalization ID}';

    protected $description = 'Validate legacy canonical snapshots; dry-run unless --apply is supplied. Never submits transactions.';

    public function handle(PayrollSnapshotService $snapshots): int
    {
        $id = $this->option('finalization');
        if ($id !== null && (! is_string($id) || ! preg_match('/\A[1-9][0-9]{0,17}\z/', $id))) {
            $this->error('Provide a positive finalization ID.');

            return self::FAILURE;
        }
        $query = PayrollFinalization::query()->when($id !== null, fn ($query) => $query->whereKey($id));
        if ($id !== null && ! $query->exists()) {
            $this->error('Finalization not found.');

            return self::FAILURE;
        }
        $checked = 0;
        $blocked = 0;
        $this->info($this->option('apply') ? 'Apply mode: valid records are archived individually.' : 'Dry run: no records will be written.');
        foreach ($query->lazyById(100) as $finalization) {
            try {
                $snapshots->archive($finalization, (bool) $this->option('apply'));
                $checked++;
            } catch (Throwable) {
                // No frozen payroll bytes or SQL/provider exceptions in console output.
                $this->error('Blocked finalization '.$finalization->id.'; inspect its retained snapshot and digest.');
                $blocked++;
            }
        }
        $this->info("Validated: {$checked}; blocked: {$blocked}.");

        return $blocked === 0 ? self::SUCCESS : self::FAILURE;
    }
}
