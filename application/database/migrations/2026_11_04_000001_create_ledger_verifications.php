<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * System-wide, read-only integrity verification of the retained payroll ledger.
     *
     * Per-payroll checks already live in `payroll_verifications`. This table retains
     * the administrator-initiated checks that walk every archived finalized payroll
     * record and its commitment/correction lineage. It never stores payroll values,
     * signed transaction bytes or provider messages.
     */
    public function up(): void
    {
        Schema::create('ledger_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->enum('status', ['verified', 'issues_found']);
            $table->unsignedInteger('records_checked')->default(0);
            $table->unsignedInteger('issues_found')->default(0);
            $table->json('report')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('ledger_verifications')->exists()) {
            throw new LogicException('Retained ledger verification history prevents rollback.');
        }
        Schema::dropIfExists('ledger_verifications');
    }
};
