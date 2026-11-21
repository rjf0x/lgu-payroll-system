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
        Schema::create('payroll_processing_locks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('payroll_processing_locks')->insert(['id' => 1]);

        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->text('purpose')->nullable();
            $table->unsignedBigInteger('replaces_payroll_run_id')->nullable()->unique();
            $table->foreign('replaces_payroll_run_id')->references('payroll_run_id')->on('payroll_runs')->restrictOnDelete();
        });
        Schema::table('payroll_details', function (Blueprint $table): void {
            $table->date('coverage_start')->nullable();
            $table->date('coverage_end')->nullable();
            $table->string('compensation_reference', 255)->nullable();
            $table->text('compensation_reason')->nullable();
            $table->text('accounting_basis')->nullable();
            $table->text('overlap_justification')->nullable();
            $table->unsignedBigInteger('original_payroll_detail_id')->nullable();
            $table->foreign('original_payroll_detail_id')->references('payroll_detail_id')->on('payroll_details')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table): void {
            $table->dropForeign(['original_payroll_detail_id']);
            $table->dropColumn(['coverage_start', 'coverage_end', 'compensation_reference', 'compensation_reason', 'accounting_basis', 'overlap_justification', 'original_payroll_detail_id']);
        });
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropForeign(['replaces_payroll_run_id']);
            $table->dropUnique(['replaces_payroll_run_id']);
            $table->dropColumn(['purpose', 'replaces_payroll_run_id']);
        });
        Schema::dropIfExists('payroll_processing_locks');
    }
};
