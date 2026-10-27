<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_finalization_id')->unique()->constrained()->restrictOnDelete();
            $table->string('canonicalization', 50);
            $table->string('algorithm', 10);
            $table->char('sha256', 64);
            $table->uuid('submission_key')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('payroll_anchor_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_commitment_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['submitting', 'unavailable', 'failed', 'pending', 'unknown']);
            $table->string('network', 120)->nullable();
            $table->string('transaction_reference', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->index(['payroll_commitment_id', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('payroll_commitments')->exists()) {
            throw new LogicException('Retained payroll commitments prevent rollback.');
        }
        Schema::dropIfExists('payroll_anchor_attempts');
        Schema::dropIfExists('payroll_commitments');
    }
};
