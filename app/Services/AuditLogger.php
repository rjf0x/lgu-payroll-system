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
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
