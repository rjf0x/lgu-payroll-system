<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->decimal('payable_days', 8, 2)->nullable();
            $table->string('input_reference', 255)->nullable();
            $table->foreignId('input_recorded_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('input_confirmed_at')->nullable();
            $table->boolean('manual_adjustments')->default(false);
            $table->dropColumn(['days_worked', 'days_present', 'days_absent', 'days_on_leave']);
        });

        foreach (['leave_transactions', 'leave_applications', 'leave_balances', 'leave_types', 'attendance_adjustments', 'attendance_records'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::table('permissions')->whereIn('permission_name', ['attendance.manage', 'leave.manage'])->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('input_recorded_by');
            $table->dropColumn(['payable_days', 'input_reference', 'input_confirmed_at', 'manual_adjustments']);
            foreach (['days_worked', 'days_present', 'days_absent', 'days_on_leave'] as $column) {
                $table->decimal($column, 8, 2)->default(0);
            }
        });
        /** Removed module data cannot be restored by rollback; only its schema is recreated. */
        foreach (['000017_create_attendance_records', '000018_create_attendance_adjustments', '000019_create_leave_types', '000020_create_leave_balances', '000021_create_leave_applications', '000022_create_leave_transactions'] as $migration) {
            (require database_path('migrations/2026_01_01_'.$migration.'_table.php'))->up();
        }
    }
};
