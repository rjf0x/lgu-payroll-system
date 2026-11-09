<?php

namespace Tests\Unit;

use App\Services\Blockchain\PayrollCanonicalizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PayrollCanonicalizerTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    #[DataProvider('vectors')]
    public function test_matches_the_published_version_one_bytes_and_digest(array $payload, string $hash, int $length): void
    {
        $canonicalizer = new PayrollCanonicalizer;

        $this->assertSame($hash, $canonicalizer->hash($payload));
        $this->assertSame($length, strlen($canonicalizer->serialize($payload)));
    }

    /** @return array<string, array{array<string, mixed>, string, int}> */
    public static function vectors(): array
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/besu-phase-1-hash-vectors.json'), true, 512, JSON_THROW_ON_ERROR);
        $cases = [];
        foreach ($fixture['vectors'] as $vector) {
            $cases[$vector['name']] = [$vector['payload'], $vector['expected_sha256'], $vector['canonical_byte_length']];
        }

        return $cases;
    }

    #[DataProvider('invalidValues')]
    public function test_rejects_values_outside_the_version_one_contract(string $path, mixed $value): void
    {
        $payload = self::vectors()['baseline'][0];
        $reference = &$payload;
        foreach (explode('.', $path) as $key) {
            $reference = &$reference[$key];
        }
        $reference = $value;
        unset($reference);

        $this->expectException(InvalidArgumentException::class);
        (new PayrollCanonicalizer)->serialize($payload);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidValues(): array
    {
        return [
            'unknown version' => ['hash_schema_version', 2], 'string revision' => ['revision', '1'],
            'zero revision' => ['revision', 0], 'overflow revision' => ['revision', 4294967296],
            'wrong currency' => ['currency', 'USD'], 'invalid namespace' => ['organization_id', '-lgu'],
            'wrong UUID version' => ['payroll_reference', '123e4567-e89b-12d3-a456-426614174000'],
            'float money' => ['details.0.net_pay', 19000.00], 'extra precision' => ['details.0.net_pay', '19000.001'],
            'exponent' => ['details.0.net_pay', '19e3'], 'zero employment' => ['details.0.employment_id', '0'],
            'leading zero identity' => ['run.created_by', '010'], 'invalid boolean' => ['details.0.manual_adjustments', 0],
            'invalid UTF8' => ['details.0.employee_name_snapshot', "\xff"], 'missing required snapshot' => ['details.0.department_snapshot', null],
            'empty details' => ['details', []], 'invalid date' => ['period.start_date', '2026-02-31'],
            'non UTC timestamp' => ['run.finalized_at', '2026-11-09T03:00:00+00:00'],
            'unfinalized status' => ['run.status', 'APPROVED'], 'extra field' => ['details.0.unexpected', 'value'],
        ];
    }

    public function test_requires_every_declared_field(): void
    {
        $payload = self::vectors()['baseline'][0];
        unset($payload['details'][0]['gross_pay']);

        $this->expectException(InvalidArgumentException::class);
        (new PayrollCanonicalizer)->serialize($payload);
    }

    /** @return array<string, array{string, mixed}> */
    public static function coveredFields(): array
    {
        $cases = [];
        $walk = function (array $value, string $prefix = '') use (&$walk, &$cases): void {
            foreach ($value as $key => $item) {
                $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                if (is_array($item)) {
                    $walk($item, $path);
                } elseif (! in_array($path, ['hash_schema_version', 'currency', 'run.status'], true)) {
                    $changed = match (true) {
                        $path === 'payroll_reference' => '123e4567-e89b-42d3-a456-426614174001',
                        $path === 'organization_id' => 'lgu-example-other',
                        $path === 'revision' => 2,
                        is_bool($item) => ! $item,
                        $item === null => str_ends_with($path, 'remarks') ? '' : '0.00',
                        preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $item) === 1 => substr($item, 0, 8).'15',
                        str_ends_with($item, 'Z') => substr($item, 0, -3).'01Z',
                        preg_match('/^[0-9]+\.[0-9]{2}$/', $item) === 1 => $item === '0.00' ? '0.01' : '0.00',
                        ctype_digit($item) => $item.'1',
                        default => $item.' changed',
                    };
                    $cases[$path] = [$path, $changed];
                }
            }
        };
        $walk(self::vectors()['baseline'][0]);

        return $cases;
    }

    #[DataProvider('coveredFields')]
    public function test_each_covered_scalar_changes_the_digest(string $path, mixed $value): void
    {
        $payload = self::vectors()['baseline'][0];
        $reference = &$payload;
        foreach (explode('.', $path) as $key) {
            $reference = &$reference[$key];
        }
        $reference = $value;
        unset($reference);

        $this->assertNotSame(self::vectors()['baseline'][1], (new PayrollCanonicalizer)->hash($payload));
    }

    public function test_sorts_numeric_employee_identifiers_and_line_bytes_and_preserves_duplicates(): void
    {
        $payload = self::vectors()['unicode-and-delimiters'][0];
        $second = $payload['details'][0];
        $second['employment_id'] = '2';
        $payload['details'][] = $second;
        $canonicalizer = new PayrollCanonicalizer;
        $expected = $canonicalizer->serialize($payload);
        $payload['details'] = array_reverse($payload['details']);
        foreach ($payload['details'] as &$detail) {
            $detail['earnings'] = array_reverse($detail['earnings']);
        }
        unset($detail);
        $this->assertSame($expected, $canonicalizer->serialize($payload));
        $this->assertSame('2', json_decode($expected, true)['details'][0]['employment_id']);
        $payload['details'][0]['earnings'][] = $payload['details'][0]['earnings'][0];
        $withDuplicate = $canonicalizer->serialize($payload);
        $this->assertNotSame($expected, $withDuplicate);
        $this->assertCount(3, json_decode($withDuplicate, true)['details'][0]['earnings']);
    }

    public function test_rejects_duplicate_employments(): void
    {
        $payload = self::vectors()['baseline'][0];
        $payload['details'][] = $payload['details'][0];

        $this->expectException(InvalidArgumentException::class);
        (new PayrollCanonicalizer)->serialize($payload);
    }

    public function test_preserves_large_decimals_and_normalizes_zero_without_float_conversion(): void
    {
        $payload = self::vectors()['baseline'][0];
        $payload['details'][0]['net_pay'] = '9999999999999.99';
        $payload['details'][0]['lbp_loan_deduction'] = '-000.0000';
        $canonical = (new PayrollCanonicalizer)->serialize($payload);

        $this->assertStringContainsString('"net_pay":"9999999999999.99"', $canonical);
        $this->assertStringContainsString('"lbp_loan_deduction":"0.00"', $canonical);
        $this->assertFalse(str_ends_with($canonical, "\n"));
    }

    public function test_keeps_empty_remarks_distinct_from_null_and_preserves_unicode_line_separators(): void
    {
        $payload = self::vectors()['baseline'][0];
        $canonicalizer = new PayrollCanonicalizer;
        $original = $canonicalizer->hash($payload);
        $payload['run']['approval']['remarks'] = '';
        $this->assertNotSame($original, $canonicalizer->hash($payload));
        $payload['details'][0]['employee_name_snapshot'] = "Example\u{2028}Name";
        $this->assertStringContainsString("Example\u{2028}Name", $canonicalizer->serialize($payload));
    }
}
