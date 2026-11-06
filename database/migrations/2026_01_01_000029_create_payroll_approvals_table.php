<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_approvals', function (Blueprint $table) {
            $table->id('approval_id');
            $table->foreignId('payroll_run_id')->constrained('payroll_runs', 'payroll_run_id')->cascadeOnDelete();
            $table->string('approval_step', 50);
            $table->string('action', 30);
            $table->foreignId('acted_by')->constrained('users', 'user_id');
            $table->text('remarks')->nullable();
            $table->dateTime('acted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_approvals');
    }
};
