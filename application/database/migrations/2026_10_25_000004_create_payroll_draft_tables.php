<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->boolean('is_demonstration');
            $table->enum('status', ['draft'])->default('draft');
            $table->unsignedInteger('record_version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['period_start', 'is_demonstration']);
        });
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_salary_record_id')->constrained()->restrictOnDelete();
            $table->string('employee_number', 50);
            $table->string('employee_name', 350);
            $table->string('position', 150);
            $table->string('office', 150);
            $table->enum('personnel_classification', ['permanent', 'coterminous', 'part_time']);
            $table->unsignedSmallInteger('grade');
            $table->unsignedSmallInteger('step');
            $table->decimal('monthly_salary', 12, 2);
            $table->timestamps();
            $table->unique(['payroll_batch_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_batches');
    }
};
