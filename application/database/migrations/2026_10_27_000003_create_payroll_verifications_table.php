<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('payroll_finalization_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_commitment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payroll_anchor_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('status', ['not_anchored', 'unavailable', 'pending', 'failed', 'mismatched', 'verified', 'invalid_snapshot']);
            $table->enum('local_status', ['not_prepared', 'match', 'mismatch', 'invalid']);
            $table->string('reason_code', 50);
            $table->char('current_hash', 64)->nullable();
            $table->char('saved_hash', 64)->nullable();
            $table->char('network_hash', 64)->nullable();
            $table->string('network', 120)->nullable();
            $table->string('transaction_reference', 255)->nullable();
            $table->string('block_reference', 255)->nullable();
            $table->unsignedInteger('confirmations')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['payroll_finalization_id', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('payroll_verifications')->exists()) {
            throw new LogicException('Retained payroll verification history prevents rollback.');
        }
        Schema::dropIfExists('payroll_verifications');
    }
};
