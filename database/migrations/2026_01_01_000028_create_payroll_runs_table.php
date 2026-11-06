<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id('payroll_run_id');
            $table->foreignId('payroll_period_id')->constrained('payroll_periods', 'payroll_period_id')->cascadeOnDelete();
            $table->unsignedInteger('run_number');
            $table->string('run_type', 30);
            $table->string('status', 30);
            $table->foreignId('created_by')->constrained('users', 'user_id');
            $table->dateTime('processed_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'run_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
