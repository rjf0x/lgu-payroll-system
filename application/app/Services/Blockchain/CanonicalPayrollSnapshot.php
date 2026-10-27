<?php

namespace App\Services\Blockchain;

use InvalidArgumentException;

/** Application-specific canonical JSON, not RFC 8785. Freeze v1 before any live use. */
class CanonicalPayrollSnapshot
{
    public const VERSION = 'payroll-commitment-json-v1';

    public function encode(array $value): string
    {
        return json_encode($this->ordered($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function encodeFinalization(int $batchId, int $finalizationId, int $version, array $snapshot): string
    {
        return $this->encode([
            'canonicalization' => self::VERSION,
            'payroll_batch_id' => $batchId, 'payroll_finalization_id' => $finalizationId,
            'version' => $version, 'snapshot' => $snapshot,
        ]);
    }

    private function ordered(mixed $value): mixed
    {
        if (is_array($value)) {
            $list = array_is_list($value);
            foreach ($value as $key => $member) {
                $value[$key] = $this->ordered($member);
            }
            if (! $list) {
                ksort($value, SORT_STRING);
            }

            // Preserve a map as a JSON object even when sorting numeric keys
            // would otherwise turn its PHP representation into a list.
            return $list ? $value : (object) $value;
        }
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        throw new InvalidArgumentException('Canonical payroll values must not contain floats or objects.');
    }
}
