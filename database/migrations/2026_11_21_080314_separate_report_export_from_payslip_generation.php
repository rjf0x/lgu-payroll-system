<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $timestamp = now()->toDateTimeString();
            DB::table('permissions')->insertOrIgnore([
                'permission_name' => 'reports.export', 'description' => 'Export authorized report spreadsheets.',
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]);
            $exportId = DB::table('permissions')->where('permission_name', 'reports.export')->value('permission_id');
            $generateId = DB::table('permissions')->where('permission_name', 'reports.generate')->value('permission_id');
            $legacyRoleIds = $generateId === null ? collect() : DB::table('role_permissions')->where('permission_id', $generateId)->pluck('role_id');
            $exportRoleIds = DB::table('roles')->whereIn('role_name', ['Admin', 'Payroll Officer', 'Approver', 'Viewer'])
                ->pluck('role_id')->merge($legacyRoleIds)->unique()->values();
            $added = 0;
            foreach ($exportRoleIds as $roleId) {
                $added += DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId, 'permission_id' => $exportId, 'created_at' => $timestamp,
                ]);
            }
            $viewerRoleIds = DB::table('roles')->where('role_name', 'Viewer')->pluck('role_id');
            $removed = $generateId === null ? 0 : DB::table('role_permissions')
                ->whereIn('role_id', $viewerRoleIds)->where('permission_id', $generateId)->delete();
            DB::table('permissions')->where('permission_name', 'reports.generate')->update([
                'description' => 'Generate payslips and publish finalized payroll statements.', 'updated_at' => $timestamp,
            ]);
            if ($added > 0 || $removed > 0) {
                DB::table('audit_logs')->insert([
                    'user_id' => null, 'action' => 'REPORT_PERMISSIONS_SPLIT', 'module' => 'Security',
                    'record_table' => 'role_permissions', 'record_id' => null,
                    'old_values' => json_encode(['legacy_export_role_ids' => $legacyRoleIds->all()], JSON_THROW_ON_ERROR),
                    'new_values' => json_encode([
                        'export_role_ids' => $exportRoleIds->all(), 'viewer_role_ids' => $viewerRoleIds->all(), 'viewer_write_grants_removed' => $removed,
                        'reason' => 'F08: separate report exports from payslip generation/publication.', 'actor' => 'system migration',
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $timestamp,
                ]);
            }
        });
    }

    /**
     * Retain the permission hardening on rollback; never restore Viewer write access.
     */
    public function down(): void {}
};
