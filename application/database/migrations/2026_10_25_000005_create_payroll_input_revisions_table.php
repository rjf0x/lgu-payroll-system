<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_input_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_salary_record_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->json('entry_snapshot');
            $table->json('payload');
            $table->string('authority_reference', 255);
            $table->string('change_reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['payroll_batch_id', 'employee_id', 'revision'], 'payroll_input_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_input_revisions');
    }
};
