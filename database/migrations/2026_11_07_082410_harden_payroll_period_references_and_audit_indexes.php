<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropForeign(['payroll_period_id']);
            $table->foreign('payroll_period_id')->references('payroll_period_id')->on('payroll_periods')->restrictOnDelete();
        });
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropForeign(['payroll_period_id']);
            $table->foreign('payroll_period_id')->references('payroll_period_id')->on('payroll_periods')->cascadeOnDelete();
        });
    }
};
