<?php

namespace App\Services\Blockchain;

use App\Models\PayrollRegistration;
use App\Models\PayrollRun;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;

class PayrollPayloadBuilder
{
    /** @return array<string, mixed> */
    public function build(PayrollRun $run, PayrollRegistration $registration): array
    {
        $run->load(['details.earnings', 'details.deductions', 'details.employerContributions']);

        return [
            'hash_schema_version' => $registration->hash_schema_version,
            'organization_id' => $registration->organization_id,
            'payroll_reference' => $registration->payroll_reference,
            'revision' => $registration->revision,
            'currency' => 'PHP',
            'period' => $registration->period_snapshot,
            'run' => [...$this->values($run, PayrollCanonicalizer::RUN), 'approval' => $registration->approval_snapshot],
            'details' => $run->details->map(fn ($detail): array => [
                ...$this->values($detail, PayrollCanonicalizer::DETAIL),
                'earnings' => $detail->earnings->map(fn ($line): array => $this->values($line, PayrollCanonicalizer::EARNING))->values()->all(),
                'deductions' => $detail->deductions->map(fn ($line): array => $this->values($line, PayrollCanonicalizer::DEDUCTION))->values()->all(),
                'employer_contributions' => $detail->employerContributions->map(fn ($line): array => $this->values($line, PayrollCanonicalizer::CONTRIBUTION))->values()->all(),
            ])->values()->all(),
        ];
    }

    /** @param array<string, string> $schema
     * @return array<string, mixed>
     */
    public function values(Model $model, array $schema): array
    {
        $values = [];
        foreach ($schema as $field => $type) {
            $type = ltrim($type, '?');
            if (in_array($type, ['object', 'list'], true)) {
                continue;
            }
            $value = $model->getAttribute($field);
            $values[$field] = match (true) {
                $field === 'input_reference' && $value === null => '',
                $value === null => null,
                $type === 'id' => (string) $value,
                $type === 'timestamp' && $value instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                $type === 'date' && $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                default => $value,
            };
        }

        return $values;
    }
}
