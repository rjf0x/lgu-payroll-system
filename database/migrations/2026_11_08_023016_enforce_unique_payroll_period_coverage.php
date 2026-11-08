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
        $duplicates = DB::table('payroll_periods')->select('payroll_type', 'start_date', 'end_date')
            ->groupBy('payroll_type', 'start_date', 'end_date')->havingRaw('COUNT(*) > 1')->exists();

        if ($duplicates) {
            throw new RuntimeException('Duplicate payroll period coverage exists. Resolve duplicate coverage dates and types while preserving payroll runs before retrying this migration. No payroll records have been changed.');
        }

        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->unique(['payroll_type', 'start_date', 'end_date'], 'payroll_periods_coverage_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->dropUnique('payroll_periods_coverage_unique');
        });
    }
};
