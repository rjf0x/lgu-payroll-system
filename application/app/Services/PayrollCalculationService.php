<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationItem;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayrollCalculationService
{
    public function calculate(Request $request, PayrollBatch $batch, int $version): PayrollCalculationRun
    {
        return DB::transaction(function () use ($request, $batch, $version) {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && $actor->auth_version === $request->session()->get('auth_version'), 403);
            $current = PayrollBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $current);
            if ($current->record_version !== $version) {
                throw ValidationException::withMessages(['record_version' => 'This draft changed. Reopen it before calculating.']);
            }
            if (! $current->is_demonstration) {
                throw ValidationException::withMessages(['batch' => 'Core calculations are demonstration-only until category, statutory and attendance rules are validated.']);
            }
            $entries = $current->entries()->orderBy('employee_id')->lockForUpdate()->get();
            if ($entries->isEmpty()) {
                throw ValidationException::withMessages(['employees' => 'Select covered employees and save their inputs first.']);
            }
            $employees = Employee::whereIn('id', $entries->pluck('employee_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $inputs = app(PayrollInputService::class);
            $preparation = app(PayrollPreparationService::class);
            $calculator = app(PayrollCalculator::class);
            $rows = [];
            $totals = array_fill_keys(PayrollCalculator::TOTAL_FIELDS, '0.00');
            foreach ($entries as $entry) {
                $employee = $employees->get($entry->employee_id);
                abort_unless($employee, 404);
                $source = $preparation->source($employee, $current, true);
                if ($source['issue']) {
                    throw ValidationException::withMessages(['employees' => $entry->employee_number.': '.$source['issue']]);
                }
                $context = $inputs->context($entry);
                foreach ($preparation->snapshot($employee, $source['record']) as $field => $value) {
                    if ((string) $context[$field] !== (string) $value) {
                        throw ValidationException::withMessages(['employees' => $entry->employee_number.': Refresh the saved employee/salary snapshot before calculating.']);
                    }
                }
                $revision = $inputs->revisions($current, $entry)->lockForUpdate()->first();
                if (! $revision || $inputs->contextChanged($revision, $entry)) {
                    throw ValidationException::withMessages(['inputs' => $entry->employee_number.': Save a reviewed current input revision, including explicit zero attendance, before calculating.']);
                }
                $breakdown = $calculator->calculate($entry->monthly_salary, $revision->payload);
                foreach (PayrollCalculator::TOTAL_FIELDS as $field) {
                    $totals[$field] = bcadd($totals[$field], $breakdown['totals'][$field], 2);
                }
                $rows[] = [
                    'employee_id' => $entry->employee_id, 'payroll_input_revision_id' => $revision->id,
                    'employee_salary_record_id' => $entry->employee_salary_record_id, 'entry_snapshot' => $context,
                    'input_snapshot' => ['revision' => $revision->revision, 'payload' => $revision->payload, 'authority_reference' => $revision->authority_reference, 'change_reason' => $revision->change_reason],
                    'breakdown' => $breakdown,
                ];
            }
            // Revalidate all sources before returning even an existing version.
            $existing = PayrollCalculationRun::where('payroll_batch_id', $current->id)->where('batch_version', $version)->where('engine_version', PayrollCalculator::VERSION)->first();
            if ($existing) {
                return $existing;
            }
            $run = PayrollCalculationRun::forceCreate([
                'payroll_batch_id' => $current->id, 'batch_version' => $version, 'engine_version' => PayrollCalculator::VERSION,
                'status' => 'provisional', 'totals' => $totals, 'created_by' => $actor->id,
            ]);
            foreach ($rows as $row) {
                $run->items()->save((new PayrollCalculationItem)->forceFill($row));
            }

            return $run;
        }, 3);
    }

    public function itemIssue(PayrollCalculationItem $item, PayrollBatch $batch): ?string
    {
        $entry = PayrollEntry::with('employee')->where('payroll_batch_id', $batch->id)->where('employee_id', $item->employee_id)->first();
        if (! $entry) {
            return 'Employee is no longer covered by this draft.';
        }
        $inputs = app(PayrollInputService::class);
        $latest = $inputs->revisions($batch, $entry)->first();
        if (! $latest || $latest->id !== $item->payroll_input_revision_id || $inputs->contextChanged($latest, $entry)) {
            return 'Input revision or saved context changed. Review and calculate a new draft version.';
        }
        if ($item->entry_snapshot != $inputs->context($entry)) {
            return 'The saved employee/salary snapshot changed.';
        }

        return app(PayrollPreparationService::class)->entryIssue($entry, $batch);
    }
}
