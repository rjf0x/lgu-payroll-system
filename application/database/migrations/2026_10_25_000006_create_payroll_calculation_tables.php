<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_calculation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('batch_version');
            $table->string('engine_version', 50);
            $table->enum('status', ['provisional'])->default('provisional');
            $table->json('totals');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['payroll_batch_id', 'batch_version', 'engine_version'], 'payroll_calculation_version_unique');
        });
        Schema::create('payroll_calculation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_calculation_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_input_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_salary_record_id')->constrained()->restrictOnDelete();
            $table->json('entry_snapshot');
            $table->json('input_snapshot');
            $table->json('breakdown');
            $table->unique(['payroll_calculation_run_id', 'employee_id'], 'payroll_calculation_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_calculation_items');
        Schema::dropIfExists('payroll_calculation_runs');
    }
};
