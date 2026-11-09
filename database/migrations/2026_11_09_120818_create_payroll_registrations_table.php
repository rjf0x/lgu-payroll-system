<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_registrations', function (Blueprint $table): void {
            $table->id('registration_id');
            $table->foreignId('payroll_run_id')->unique()->constrained('payroll_runs', 'payroll_run_id')->restrictOnDelete();
            $table->string('organization_id', 63);
            $table->uuid('payroll_reference');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('hash_schema_version');
            $table->char('payload_hash', 64);
            $table->char('idempotency_key', 64)->unique();
            $table->longText('canonical_payload');
            $table->json('period_snapshot');
            $table->json('approval_snapshot');
            $table->json('target_network');
            $table->string('status', 30)->default('QUEUED');
            $table->string('transaction_hash', 66)->nullable();
            $table->json('evidence')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 100)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'payroll_reference', 'revision'], 'payroll_registration_identity_unique');
            $table->index(['status', 'next_attempt_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_registrations');
    }
};
