<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Writes structured records to the audit trail.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        string $module,
        ?int $userId = null,
        ?string $recordTable = null,
        ?string $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'record_table' => $recordTable,
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? $this->redact($oldValues) : null,
            'new_values' => $newValues !== null ? $this->redact($newValues) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /** @param array<string|int, mixed> $values
     * @return array<string|int, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            $normalized = strtolower(str_replace('-', '_', (string) $key));
            if (in_array($normalized, ['password', 'password_hash', 'remember_token', 'api_key', 'api_secret', 'access_token', 'refresh_token', 'authorization', 'cookie'], true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
