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
        Schema::create('payroll_registration_verifications', function (Blueprint $table): void {
            $table->id('verification_id');
            $table->foreignId('registration_id')->constrained('payroll_registrations', 'registration_id')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->char('stored_hash', 64)->nullable();
            $table->char('calculated_hash', 64)->nullable();
            $table->char('captured_hash', 64);
            $table->string('verification_result', 30);
            $table->string('remarks');
            $table->json('evidence')->nullable();
            $table->timestamp('verified_at');
            $table->index(['registration_id', 'verified_at'], 'payroll_registry_verification_time_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_registration_verifications');
    }
};
