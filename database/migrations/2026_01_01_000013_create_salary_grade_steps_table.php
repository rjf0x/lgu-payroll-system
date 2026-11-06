<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_grade_steps', function (Blueprint $table) {
            $table->id('grade_step_id');
            $table->foreignId('salary_schedule_id')->constrained('salary_schedules', 'salary_schedule_id')->cascadeOnDelete();
            $table->unsignedInteger('grade_number');
            $table->unsignedInteger('step_number');
            $table->decimal('monthly_salary', 15, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['salary_schedule_id', 'grade_number', 'step_number'], 'salary_grade_steps_grade_step_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_grade_steps');
    }
};
