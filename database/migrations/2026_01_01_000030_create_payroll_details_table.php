<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id('payroll_detail_id');
            $table->foreignId('payroll_run_id')->constrained('payroll_runs', 'payroll_run_id')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees', 'employee_id');
            $table->foreignId('employment_id')->constrained('employment_records', 'employment_id');

            // Historical snapshots preserve payroll meaning after source records change.
            $table->string('employee_number_snapshot', 50);
            $table->string('employee_name_snapshot', 255);
            $table->string('department_snapshot', 150)->nullable();
            $table->string('position_snapshot', 150)->nullable();
            $table->unsignedInteger('salary_grade_snapshot')->nullable();
            $table->unsignedInteger('salary_step_snapshot')->nullable();
            $table->decimal('salary_rate_snapshot', 15, 2)->nullable();
            $table->string('pay_basis_snapshot', 30)->nullable();

            $table->decimal('basic_pay', 15, 2);
            $table->decimal('total_earnings', 15, 2);
            $table->decimal('gross_pay', 15, 2);
            $table->decimal('total_deductions', 15, 2);
            $table->decimal('nthp_before_lbp', 15, 2);
            $table->decimal('lbp_loan_deduction', 15, 2)->default(0.00);
            $table->decimal('net_pay', 15, 2);

            $table->decimal('days_worked', 8, 2);
            $table->decimal('days_present', 8, 2);
            $table->decimal('days_absent', 8, 2);
            $table->decimal('days_on_leave', 8, 2);
            $table->string('payroll_status', 30);
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
