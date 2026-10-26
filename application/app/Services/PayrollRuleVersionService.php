<?php

namespace App\Services;

use App\Http\Requests\ActivatePayrollRuleVersionRequest;
use App\Http\Requests\SavePayrollRuleVersionRequest;
use App\Models\PayrollRuleEvent;
use App\Models\PayrollRuleVersion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PayrollRuleVersionService
{
    private function actor(Request $request): User
    {
        $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        abort_unless($actor->is_active && $actor->auth_version === $request->session()->get('auth_version'), 403);
        Gate::forUser($actor)->authorize('create', PayrollRuleVersion::class);

        return $actor;
    }

    public function parameters(array $data): array
    {
        $parameters = $data['parameters'];
        foreach ($parameters['contributions'] as &$rule) {
            foreach ($rule as $field => &$value) {
                if (! in_array($field, ['source', 'scope'], true)) {
                    $value = bcadd($value, '0', 2);
                }
            }
            unset($value);
        }
        unset($rule);
        foreach (['gsis' => 'percentage', 'pagibig' => 'tiered_percentage', 'philhealth' => 'shared_premium'] as $component => $method) {
            $parameters['contributions'][$component]['method'] = $method;
        }
        $parameters['tax']['bonus_exemption'] = bcadd($parameters['tax']['bonus_exemption'], '0', 2);
        foreach (['monthly_brackets', 'annual_brackets'] as $table) {
            foreach ($parameters['tax'][$table] as &$row) {
                $row = array_map(fn ($value) => bcadd($value, '0', 2), $row);
            }
            unset($row);
        }

        return array_merge([
            'id' => 'maintained-profile', 'label' => $data['name'],
            'review_year' => (int) substr($data['effective_from'], 0, 4),
            'rounding' => 'half_up_2_at_component_total',
            'category_validation' => [$data['personnel_classification'] => 'unverified'],
        ], $parameters);
    }

    private function validateParameters(array $parameters): void
    {
        $contributions = $parameters['contributions'] ?? [];
        if (is_array($contributions)) {
            foreach ($contributions as &$contribution) {
                if (is_array($contribution)) {
                    unset($contribution['method']);
                }
            }
            unset($contribution);
        }
        $rules = array_filter((new SavePayrollRuleVersionRequest)->rules(), fn ($key) => str_starts_with($key, 'parameters'), ARRAY_FILTER_USE_KEY);
        Validator::make(['parameters' => ['contributions' => $contributions, 'tax' => $parameters['tax'] ?? null]], $rules)->validate();
        $errors = app(StatutoryCalculator::class)->profileErrors($parameters);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function event(PayrollRuleVersion $version, User $actor, string $action, string $reason): void
    {
        $snapshot = $version->only(['id', 'name', 'personnel_classification', 'is_demonstration', 'effective_from',
            'record_version', 'parameters', 'approval_reference', 'validation_reference', 'activated_at', 'activated_by']);
        $snapshot['effective_from'] = $version->effective_from->format('Y-m-d');
        if ($action === 'activated') {
            $snapshot['activation_confirmation'] = [
                'authorization_confirmed' => true,
                'client_validation_confirmed' => ! $version->is_demonstration,
            ];
        }
        PayrollRuleEvent::forceCreate([
            'payroll_rule_version_id' => $version->id, 'action' => $action,
            'actor_id' => $actor->id, 'actor_username' => $actor->username, 'reason' => $reason,
            'snapshot' => $snapshot,
        ]);
    }

    private function canonical(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values);
        }
        foreach ($values as &$value) {
            if (is_array($value)) {
                $value = $this->canonical($value);
            }
        }
        unset($value);

        return $values;
    }

    private function fingerprint(array $attributes, string $reason): string
    {
        return hash('sha256', json_encode($this->canonical([$attributes, $reason]), JSON_THROW_ON_ERROR));
    }

    private function matchesCreation(PayrollRuleVersion $version, string $hash): bool
    {
        if (hash_equals($version->creation_hash, $hash)) {
            return true;
        }
        // Older fingerprints depended on object key order. Compare the immutable
        // creation event, rather than the potentially edited draft or stored hash.
        $event = PayrollRuleEvent::where('payroll_rule_version_id', $version->id)->where('action', 'created')->orderBy('id')->first();
        $fields = ['name', 'personnel_classification', 'is_demonstration', 'effective_from', 'parameters'];
        $attributes = array_intersect_key($event?->snapshot ?? [], array_flip($fields));
        if (count($attributes) !== count($fields) || $event->effectiveDate() === null) {
            return false;
        }
        $attributes['effective_from'] = $event->effectiveDate();

        return hash_equals($this->fingerprint($attributes, $event->reason), $hash);
    }

    public function save(Request $request, array $data, ?PayrollRuleVersion $version = null): PayrollRuleVersion
    {
        try {
            return DB::transaction(function () use ($request, $data, $version) {
                $actor = $this->actor($request);
                $data = Validator::make($data, (new SavePayrollRuleVersionRequest)->rules())->validate();
                if (substr($data['effective_from'], -2) !== '01') {
                    throw ValidationException::withMessages(['effective_from' => 'Use the first day of a month. Mid-month configuration treatment is not supported.']);
                }
                $parameters = $this->parameters($data);
                $this->validateParameters($parameters);
                $attributes = [
                    'name' => $data['name'], 'personnel_classification' => $data['personnel_classification'],
                    'is_demonstration' => (bool) $data['is_demonstration'], 'effective_from' => $data['effective_from'], 'parameters' => $parameters,
                ];
                // A normalized creation fingerprint makes the initial form idempotent.
                $hash = $this->fingerprint($attributes, $data['change_reason']);
                if (! $version) {
                    $existing = PayrollRuleVersion::where('submission_key', $data['submission_key'])->lockForUpdate()->first();
                    if ($existing) {
                        if ($existing->created_by === $actor->id && $this->matchesCreation($existing, $hash)) {
                            return $existing;
                        }
                        throw ValidationException::withMessages(['submission_key' => 'This creation form was already used. Open a fresh form.']);
                    }
                    if ((int) $data['record_version'] !== 0) {
                        throw ValidationException::withMessages(['record_version' => 'Open a fresh configuration creation form.']);
                    }
                    $current = (new PayrollRuleVersion)->forceFill([
                        'submission_key' => $data['submission_key'], 'creation_hash' => $hash, 'created_by' => $actor->id, 'record_version' => 1,
                    ]);
                } else {
                    $current = PayrollRuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
                    if ($current->activated_at !== null) {
                        throw ValidationException::withMessages(['version' => 'Activated versions are locked. Create a new dated draft.']);
                    }
                    if ($current->record_version !== (int) $data['record_version'] || $current->submission_key !== $data['submission_key']) {
                        throw ValidationException::withMessages(['record_version' => 'This draft changed. Reload its saved values before editing.']);
                    }
                    $current->record_version++;
                }
                $current->forceFill($attributes + ['updated_by' => $actor->id])->save();
                $this->event($current, $actor, $version ? 'revised' : 'created', $data['change_reason']);

                return $current;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['submission_key' => 'This creation form was already used. Reopen the saved version.']);
        }
    }

    public function activate(Request $request, PayrollRuleVersion $version, array $data): PayrollRuleVersion
    {
        try {
            return DB::transaction(function () use ($request, $version, $data) {
                $actor = $this->actor($request);
                $data = Validator::make($data, (new ActivatePayrollRuleVersionRequest)->rules())->validate();
                $current = PayrollRuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
                if (! $current->is_demonstration && ! in_array($data['client_validation_confirmed'] ?? null, [true, 1, '1', 'yes', 'on', 'true'], true)) {
                    throw ValidationException::withMessages(['client_validation_confirmed' => 'Record confirmation of external client approval and verified examples before activating this configuration. Real payroll remains disabled.']);
                }
                if ($current->activated_at !== null) {
                    $event = PayrollRuleEvent::where('payroll_rule_version_id', $current->id)->where('action', 'activated')->firstOrFail();
                    if ($current->activated_by === $actor->id && $current->record_version === (int) $data['record_version'] + 1
                        && $current->approval_reference === $data['approval_reference'] && $current->validation_reference === $data['validation_reference']
                        && $event->reason === $data['activation_reason']) {
                        return $current;
                    }
                    throw ValidationException::withMessages(['version' => 'This version is already activated and locked.']);
                }
                if ($current->record_version !== (int) $data['record_version']) {
                    throw ValidationException::withMessages(['record_version' => 'This draft changed. Review its latest saved parameters before activation.']);
                }
                $this->validateParameters($current->parameters);
                $duplicate = PayrollRuleVersion::where('personnel_classification', $current->personnel_classification)
                    ->where('is_demonstration', $current->is_demonstration)->whereDate('effective_from', $current->effective_from)
                    ->whereNotNull('activated_at')->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['effective_from' => 'An activated version already covers this category, data use and date. Choose another month.']);
                }
                $current->forceFill([
                    'activation_slot' => 1, 'activated_at' => now(), 'activated_by' => $actor->id, 'updated_by' => $actor->id,
                    'approval_reference' => $data['approval_reference'], 'validation_reference' => $data['validation_reference'],
                    'record_version' => $current->record_version + 1,
                ])->save();
                $this->event($current, $actor, 'activated', $data['activation_reason']);

                return $current;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['effective_from' => 'Another version was activated for this category, data use and date. Reload the configuration list.']);
        }
    }

    public function applicable(string $category, string $date, bool $demonstration, bool $lock = false): ?PayrollRuleVersion
    {
        $query = PayrollRuleVersion::where('personnel_classification', $category)->where('is_demonstration', $demonstration)
            ->whereNotNull('activated_at')->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from');

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    public function profile(string $category, string $date, bool $demonstration, bool $lock = false): array
    {
        $version = $this->applicable($category, $date, $demonstration, $lock);
        if (! $version) {
            return config('payroll_statutory', []);
        }

        return array_replace($version->parameters, [
            'id' => 'payroll-rule-v'.$version->id,
            '_configuration' => [
                'version_id' => $version->id, 'effective_from' => $version->effective_from->format('Y-m-d'),
                'activated_at' => $version->activated_at->toIso8601String(), 'approval_reference' => $version->approval_reference,
                'validation_reference' => $version->validation_reference, 'is_demonstration' => $version->is_demonstration,
            ],
        ]);
    }
}
