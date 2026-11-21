<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\EmploymentRecord;
use App\Models\PayrollPeriod;
use App\Models\SalarySchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MasterDataMutationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @template TModel of Model
     *
     * @param  TModel  $record
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(Model $record, array $attributes, User $actor): Model
    {
        $context = $this->auditLogger->context('Created a payroll source record.');

        return DB::transaction(function () use ($record, $attributes, $actor, $context): Model {
            $record->fill($attributes)->save();
            $record->refresh();
            $this->employmentHistory($record, null, $actor, $context);
            $this->record($record, 'CREATED', $actor, null, $record->getAttributes(), $context);

            return $record;
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $record
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $record, array $attributes, User $actor): Model
    {
        $context = $this->auditLogger->context('Updated a payroll source record.');

        return DB::transaction(function () use ($record, $attributes, $actor, $context): Model {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            $before = $record->getAttributes();
            $record->fill($attributes);
            if (! $record->isDirty()) {
                return $record;
            }
            $record->save();
            $record->refresh();
            $this->employmentHistory($record, $before, $actor, $context);
            $this->record($record, 'UPDATED', $actor, $before, $record->getAttributes(), $context);

            return $record;
        });
    }

    public function delete(Model $record, User $actor): void
    {
        $context = $this->auditLogger->context('Deleted a payroll source record.');
        DB::transaction(function () use ($record, $actor, $context): void {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            $before = $record->getAttributes();
            $relations = match (true) {
                $record instanceof EmploymentRecord => ['compensationProfiles', 'earningAssignments', 'deductionAssignments', 'history'],
                $record instanceof SalarySchedule => ['gradeSteps'],
                default => [],
            };
            foreach ($relations as $relation) {
                $relationship = $record->{$relation}();
                $before[$relation] = $relationship->orderBy($relationship->getRelated()->getKeyName())
                    ->get()->map(fn (Model $child): array => $child->getAttributes())->all();
            }
            if ($record instanceof PayrollPeriod) {
                abort_if($record->runs()->exists(), 422, 'A period with payroll runs cannot be deleted.');
            }
            $record->delete();
            $this->record($record, 'DELETED', $actor, $before, null, $context);
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $context
     */
    private function record(Model $record, string $action, User $actor, ?array $before, ?array $after, array $context): void
    {
        $module = match (true) {
            $record instanceof Employee, $record instanceof EmploymentRecord => 'Employees',
            $record instanceof PayrollPeriod => 'Payroll',
            default => 'Configuration',
        };
        $prefix = $record instanceof PayrollPeriod ? 'PERIOD' : Str::upper(Str::snake(class_basename($record)));
        $this->auditLogger->log($prefix.'_'.$action, $module, (int) $actor->getKey(), $record->getTable(), (string) $record->getKey(), $before, [
            ...($after ?? []), 'audit_context' => $context, 'deleted' => $after === null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $context
     */
    private function employmentHistory(Model $record, ?array $before, User $actor, array $context): void
    {
        if (! $record instanceof EmploymentRecord) {
            return;
        }
        $transferred = $before !== null && ($before['department_id'] !== $record->department_id || $before['position_id'] !== $record->position_id);
        $effectiveDate = $context['effective_date'] ?? ($before === null || ($before['start_date'] ?? null) !== $record->getRawOriginal('start_date')
            ? $record->start_date->toDateString()
            : now()->setTimezone(config('app.display_timezone'))->toDateString());
        EmploymentHistory::create([
            'employment_id' => $record->getKey(),
            'from_department_id' => $before['department_id'] ?? null,
            'to_department_id' => $record->department_id,
            'from_position_id' => $before['position_id'] ?? null,
            'to_position_id' => $record->position_id,
            'change_type' => $before === null ? 'APPOINTMENT_CREATED' : ($transferred ? 'TRANSFER' : 'APPOINTMENT_UPDATED'),
            'effective_date' => $effectiveDate,
            'remarks' => $context['reason'].' (Reason source: '.$context['reason_source'].'; effective date '.($context['effective_date'] === null ? 'inferred from appointment start or recording date' : 'supplied by user').'.)',
            'recorded_by' => $actor->getKey(),
        ]);
    }
}
