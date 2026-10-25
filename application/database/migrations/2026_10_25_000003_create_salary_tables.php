<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('personnel_classification', ['permanent', 'coterminous', 'part_time']);
            $table->date('effective_from');
            $table->boolean('is_demonstration');
            $table->string('authority_reference', 255)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['personnel_classification', 'effective_from', 'is_demonstration'], 'salary_schedule_date_unique');
        });
        Schema::create('salary_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_schedule_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('grade');
            $table->unsignedSmallInteger('step');
            $table->decimal('monthly_salary', 12, 2);
            $table->timestamps();
            $table->unique(['salary_schedule_id', 'grade', 'step']);
        });
        Schema::create('employee_salary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_rate_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->enum('change_type', ['initial', 'step_increment', 'promotion', 'schedule_update']);
            $table->enum('personnel_classification', ['permanent', 'coterminous', 'part_time']);
            $table->string('position', 150);
            $table->decimal('monthly_salary', 12, 2);
            $table->boolean('is_demonstration');
            $table->date('step_basis_date');
            $table->string('authority_reference', 255);
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_records');
        Schema::dropIfExists('salary_rates');
        Schema::dropIfExists('salary_schedules');
    }
};
