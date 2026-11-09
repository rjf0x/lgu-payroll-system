<?php

namespace App\Services\Blockchain;

use DateTimeImmutable;
use InvalidArgumentException;

class PayrollCanonicalizer
{
    public const VERSION = 1;

    public const PERIOD = ['period_name' => 'text', 'payroll_type' => 'text', 'start_date' => 'date', 'end_date' => 'date', 'pay_date' => '?date'];

    public const APPROVAL = ['acted_by' => 'id', 'acted_at' => 'timestamp', 'remarks' => '?text'];

    public const RUN = ['run_number' => 'id', 'run_type' => 'text', 'status' => 'text', 'created_by' => 'id', 'submitted_by' => 'id', 'submitted_at' => 'timestamp', 'finalized_by' => 'id', 'finalized_at' => 'timestamp', 'approval' => 'object'];

    public const DETAIL = [
        'employment_id' => 'id', 'employee_number_snapshot' => 'text', 'employee_name_snapshot' => 'text',
        'department_snapshot' => 'text', 'position_snapshot' => 'text', 'salary_grade_snapshot' => '?id',
        'salary_step_snapshot' => '?id', 'salary_rate_snapshot' => 'decimal', 'pay_basis_snapshot' => 'text',
        'basic_pay' => 'decimal', 'total_earnings' => 'decimal', 'gross_pay' => 'decimal', 'total_deductions' => 'decimal',
        'nthp_before_lbp' => 'decimal', 'lbp_loan_deduction' => 'decimal', 'net_pay' => 'decimal', 'payable_days' => 'decimal',
        'input_reference' => 'text', 'input_recorded_by' => 'id', 'input_confirmed_at' => 'timestamp',
        'manual_adjustments' => 'bool', 'payroll_status' => 'text', 'earnings' => 'list', 'deductions' => 'list', 'employer_contributions' => 'list',
    ];

    public const EARNING = ['earning_type_id' => 'id', 'earning_name_snapshot' => 'text', 'quantity' => '?decimal', 'rate' => '?decimal', 'amount' => 'decimal', 'is_taxable' => 'bool', 'remarks' => '?text'];

    public const DEDUCTION = ['deduction_type_id' => 'id', 'deduction_name_snapshot' => 'text', 'amount' => 'decimal', 'remarks' => '?text'];

    public const CONTRIBUTION = ['contribution_rate_id' => 'id', 'contribution_type_snapshot' => 'text', 'amount' => 'decimal'];

    /** @param array<string, mixed> $payload */
    public function serialize(array $payload): string
    {
        $payload = $this->object($payload, [
            'hash_schema_version' => 'int', 'organization_id' => 'text', 'payroll_reference' => 'text', 'revision' => 'int',
            'currency' => 'text', 'period' => 'object', 'run' => 'object', 'details' => 'list',
        ]);
        $this->require($payload['hash_schema_version'] === self::VERSION && $payload['currency'] === 'PHP');
        $this->require($payload['revision'] > 0 && $payload['revision'] <= 4294967295);
        $this->require((bool) preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/D', $payload['organization_id']));
        $this->require((bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $payload['payroll_reference']));
        $payload['period'] = $this->object($payload['period'], self::PERIOD);
        $payload['run'] = $this->object($payload['run'], self::RUN);
        $this->require($payload['run']['status'] === 'FINALIZED');
        $payload['run']['approval'] = $this->object($payload['run']['approval'], self::APPROVAL);
        $this->require($payload['details'] !== []);
        $identifiers = [];
        foreach ($payload['details'] as &$detail) {
            $detail = $this->object($detail, self::DETAIL);
            $this->require(! isset($identifiers[$detail['employment_id']]));
            $identifiers[$detail['employment_id']] = true;
            foreach (['earnings' => self::EARNING, 'deductions' => self::DEDUCTION, 'employer_contributions' => self::CONTRIBUTION] as $name => $schema) {
                $detail[$name] = array_map(fn (mixed $row): array => $this->object($row, $schema), $detail[$name]);
                usort($detail[$name], fn (array $left, array $right): int => strcmp($this->encode($left), $this->encode($right)));
            }
        }
        unset($detail);
        usort($payload['details'], fn (array $left, array $right): int => (strlen($left['employment_id']) <=> strlen($right['employment_id'])) ?: strcmp($left['employment_id'], $right['employment_id']));

        return $this->encode($payload);
    }

    /** @param array<string, mixed> $payload */
    public function hash(array $payload): string
    {
        return hash('sha256', $this->serialize($payload));
    }

    /** @param array<string, string> $schema
     * @return array<string, mixed>
     */
    private function object(mixed $input, array $schema): array
    {
        $this->require(is_array($input) && ! array_is_list($input));
        $this->require(count($input) === count($schema) && array_diff_key($schema, $input) === []);
        foreach ($schema as $key => $type) {
            $value = $input[$key];
            if (str_starts_with($type, '?')) {
                if ($value === null) {
                    continue;
                }
                $type = substr($type, 1);
            }
            $this->require(match ($type) {
                'text' => is_string($value) && preg_match('//u', $value) === 1,
                'id' => is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1,
                'int' => is_int($value),
                'bool' => is_bool($value),
                'object' => is_array($value) && ! array_is_list($value),
                'list' => is_array($value) && array_is_list($value),
                'decimal' => is_string($value) && preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', $value) === 1,
                'date' => $this->validDate($value, 'Y-m-d'),
                'timestamp' => $this->validDate($value, 'Y-m-d\TH:i:s\Z'),
                default => false,
            });
            if ($type === 'decimal') {
                $parts = explode('.', ltrim($value, '-'), 2);
                $fraction = $parts[1] ?? '';
                $this->require(trim(substr($fraction, 2), '0') === '');
                $whole = ltrim($parts[0], '0') ?: '0';
                $normalized = $whole.'.'.str_pad(substr($fraction, 0, 2), 2, '0');
                $input[$key] = str_starts_with($value, '-') && $normalized !== '0.00' ? '-'.$normalized : $normalized;
            }
        }
        ksort($input, SORT_STRING);

        return $input;
    }

    private function validDate(mixed $value, string $format): bool
    {
        if (! is_string($value) || str_contains($value, "\0")) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $parsed !== false && $parsed->format($format) === $value;
    }

    /** @param array<array-key, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    }

    private function require(bool $valid): void
    {
        if (! $valid) {
            throw new InvalidArgumentException('Payroll integrity payload does not satisfy schema version 1.');
        }
    }
}
