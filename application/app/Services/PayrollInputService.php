<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollEntry;
use App\Models\PayrollInputRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayrollInputService
{
    public function revisions(PayrollBatch $batch, PayrollEntry $entry): Builder
    {
        return PayrollInputRevision::where('payroll_batch_id', $batch->id)->where('employee_id', $entry->employee_id)->orderByDesc('revision');
    }

    public function context(PayrollEntry $entry): array
    {
        $fields = ['employee_id', 'employee_salary_record_id', 'employee_number', 'employee_name', 'office', 'position', 'personnel_classification', 'grade', 'step', 'monthly_salary'];
        $snapshot = [];
        foreach ($fields as $field) {
            $snapshot[$field] = $field === 'monthly_salary' ? $entry->monthly_salary : $entry->getRawOriginal($field);
        }

        return $snapshot;
    }

    public function contextChanged(PayrollInputRevision $revision, PayrollEntry $entry): bool
    {
        foreach ($this->context($entry) as $field => $value) {
            if ((string) ($revision->entry_snapshot[$field] ?? '') !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    public function save(Request $request, PayrollBatch $batch, PayrollEntry $entry, array $data): PayrollInputRevision
    {
        return DB::transaction(function () use ($request, $batch, $entry, $data) {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && $actor->auth_version === $request->session()->get('auth_version'), 403);
            $current = PayrollBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $current);
            if ($current->record_version !== (int) $data['record_version']) {
                throw ValidationException::withMessages(['record_version' => 'This draft changed in another session. Reload before saving inputs.']);
            }
            $saved = $current->entries()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $employee = Employee::whereKey($saved->employee_id)->lockForUpdate()->firstOrFail();
            $preparation = app(PayrollPreparationService::class);
            $source = $preparation->source($employee, $current, true);
            if ($source['issue']) {
                throw ValidationException::withMessages(['entry' => $source['issue']]);
            }
            $snapshot = $this->context($saved);
            foreach ($preparation->snapshot($employee, $source['record']) as $field => $value) {
                if ((string) $snapshot[$field] !== (string) $value) {
                    throw ValidationException::withMessages(['entry' => 'Refresh this employee in the draft by removing and re-adding it before saving inputs. Earlier input revisions will be retained.']);
                }
            }
            $payload = [];
            foreach (array_keys(PayrollInputRevision::GROUPS) as $group) {
                $payload[$group] = array_values($data[$group] ?? []);
                foreach ($payload[$group] as &$row) {
                    foreach (['amount', 'original_amount', 'adjusted_amount'] as $field) {
                        if (array_key_exists($field, $row)) {
                            $row[$field] = bcadd($row[$field], '0', 2);
                        }
                    }
                    if ($group === 'allowances') {
                        $row['is_applicable'] = (bool) $row['is_applicable'];
                    }
                }
                unset($row);
            }
            $payload['attendance'] = [
                'absence_days' => bcadd($data['attendance']['absence_days'], '0', 2),
                'undertime_minutes' => (int) $data['attendance']['undertime_minutes'], 'notes' => $data['attendance']['notes'] ?? null,
            ];
            $revision = PayrollInputRevision::forceCreate([
                'payroll_batch_id' => $current->id, 'employee_id' => $saved->employee_id,
                'employee_salary_record_id' => $saved->employee_salary_record_id,
                'revision' => ((int) $this->revisions($current, $saved)->max('revision')) + 1,
                'entry_snapshot' => $snapshot, 'payload' => $payload, 'authority_reference' => $data['authority_reference'],
                'change_reason' => $data['change_reason'], 'created_by' => $actor->id,
            ]);
            $current->record_version++;
            $current->updated_by = $actor->id;
            $current->save();

            return $revision;
        }, 3);
    }
}
