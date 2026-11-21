<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Writes structured records to the audit trail.
 */
class AuditLogger
{
    /** @return array{reason: string, reason_source: string, route: ?string, effective_date: ?string} */
    public function context(string $purpose): array
    {
        $values = request()->validate([
            'change_reason' => ['nullable', 'string', 'max:2000'],
            'change_effective_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $reason = trim($values['change_reason'] ?? '');

        return [
            'reason' => $reason !== '' ? $reason : $purpose,
            'reason_source' => $reason !== '' ? 'user' : 'operation',
            'route' => request()->route()?->getName(),
            'effective_date' => $values['change_effective_date'] ?? null,
        ];
    }

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
        if ($userId !== null) {
            $actor = $request->user();
            if (! $actor instanceof User || (int) $actor->getKey() !== $userId) {
                $actor = User::query()->find($userId, ['user_id', 'username']);
            }
            $newValues = [
                ...($newValues ?? []),
                'audit_actor' => ['user_id' => $userId, 'username' => $actor?->username],
            ];
        }

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
    public function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            $normalized = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', str_replace('-', '_', (string) $key)));
            if (in_array($normalized, ['password', 'password_hash', 'password_confirmation', 'remember_token', 'api_key', 'api_secret', 'api_token', 'access_token', 'refresh_token', 'authorization', 'cookie', 'token', 'secret', 'client_secret', 'private_key', 'signing_key', 'bridge_token', 'csrf_token'], true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
