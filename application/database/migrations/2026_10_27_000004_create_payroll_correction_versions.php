<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->unsignedInteger('payroll_version')->default(1);
            $table->foreignId('root_payroll_batch_id')->nullable()->constrained('payroll_batches')->restrictOnDelete();
            $table->foreignId('correction_of_finalization_id')->nullable()->constrained('payroll_finalizations')->restrictOnDelete();
            $table->dropUnique(['period_start', 'is_demonstration']);
            $table->unique(['period_start', 'is_demonstration', 'payroll_version'], 'payroll_period_version_unique');
            $table->unique('correction_of_finalization_id', 'payroll_correction_parent_unique');
        });
        Schema::create('payroll_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_finalization_id')->constrained('payroll_finalizations')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->uuid('request_key')->unique();
            $table->char('source_hash', 64);
            $table->string('reason', 500);
            $table->string('authority_reference', 255);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['source_finalization_id', 'revision'], 'payroll_correction_revision_unique');
        });
        Schema::create('payroll_correction_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_correction_request_id')->unique('correction_decision_request_unique')
                ->constrained(indexName: 'correction_decision_request_foreign')->restrictOnDelete();
            $table->enum('action', ['authorize', 'return']);
            $table->foreignId('correction_batch_id')->nullable()->unique()->constrained('payroll_batches')->restrictOnDelete();
            $table->string('reason', 500);
            $table->string('authority_reference', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (DB::table('payroll_correction_requests')->exists() || DB::table('payroll_batches')->where('payroll_version', '>', 1)->exists()) {
            throw new LogicException('Retained payroll correction history prevents rollback.');
        }
        Schema::dropIfExists('payroll_correction_decisions');
        Schema::dropIfExists('payroll_correction_requests');
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropUnique('payroll_period_version_unique');
            $table->dropUnique('payroll_correction_parent_unique');
            $table->dropConstrainedForeignId('correction_of_finalization_id');
            $table->dropConstrainedForeignId('root_payroll_batch_id');
            $table->dropColumn('payroll_version');
            $table->unique(['period_start', 'is_demonstration']);
        });
    }
};
