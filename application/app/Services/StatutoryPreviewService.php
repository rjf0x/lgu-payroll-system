<?php

namespace App\Services;

use App\Models\PayrollBatch;
use App\Models\PayrollCalculationItem;
use App\Models\PayrollCalculationRun;
use App\Models\StatutoryPreview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StatutoryPreviewService
{
    private function canonical(array $value): array
    {
        // MySQL JSON reorders object keys. Preserve list order and scalar types.
        foreach ($value as &$member) {
            if (is_array($member)) {
                $member = $this->canonical($member);
            }
        }
        unset($member);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    public function save(Request $request, PayrollBatch $batch, PayrollCalculationRun $run, PayrollCalculationItem $item, array $data): StatutoryPreview
    {
        return DB::transaction(function () use ($request, $batch, $run, $item, $data) {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && $actor->auth_version === $request->session()->get('auth_version'), 403);
            $current = PayrollBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $current);
            abort_unless($run->payroll_batch_id === $current->id && $item->payroll_calculation_run_id === $run->id, 404);
            if (! $current->is_demonstration) {
                throw ValidationException::withMessages(['batch' => 'Real payroll is blocked: client-approved statutory and category rules are unavailable.']);
            }
            $calculator = app(StatutoryCalculator::class);
            $input = $calculator->normalize($data);
            if ($current->record_version !== (int) $data['record_version'] || $run->batch_version !== $current->record_version) {
                throw ValidationException::withMessages(['record_version' => 'Draft changed. Calculate a current core preview before reviewing statutory amounts.']);
            }
            // Reuse the core workflow's actor, locked whole-batch source/input checks.
            $checked = app(PayrollCalculationService::class)->calculate($request, $current, $current->record_version);
            if ($checked->id !== $run->id) {
                throw ValidationException::withMessages(['record_version' => 'Review the current core engine preview first.']);
            }
            $profile = config('payroll_statutory', []);
            $latest = StatutoryPreview::where('payroll_calculation_item_id', $item->id)->orderByDesc('revision')->lockForUpdate()->first();
            if ($latest && $this->canonical($latest->inputs) === $this->canonical($input)
                && $this->canonical($latest->parameters) === $this->canonical($profile)
                && $latest->result['engine'] === StatutoryCalculator::VERSION) {
                return $latest;
            }
            if ((int) $data['base_revision'] !== ($latest?->revision ?? 0)) {
                throw ValidationException::withMessages(['base_revision' => 'Another statutory review was saved. Reload this employee review before saving.']);
            }
            $result = $calculator->calculate($item->breakdown, $input, $profile, $item->entry_snapshot['personnel_classification'], $current->period_start->format('Y-m'));

            return StatutoryPreview::forceCreate([
                'payroll_calculation_item_id' => $item->id, 'revision' => ($latest?->revision ?? 0) + 1,
                'inputs' => $input, 'parameters' => $profile, 'result' => $result, 'created_by' => $actor->id,
            ]);
        }, 3);
    }
}
