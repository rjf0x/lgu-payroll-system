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
            $table->enum('status', ['draft', 'returned', 'submitted', 'approved', 'finalized'])->default('draft')->change();
            $table->json('coverage_preparer_ids')->nullable();
        });
        // Retain the last known pre-migration coverage editor as well as the creator.
        DB::table('payroll_batches')->orderBy('id')->each(function ($batch) {
            DB::table('payroll_batches')->where('id', $batch->id)->update([
                'coverage_preparer_ids' => json_encode(array_values(array_unique([$batch->created_by, $batch->updated_by])), JSON_THROW_ON_ERROR),
            ]);
        });
        Schema::create('payroll_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedInteger('batch_version');
            $table->foreignId('payroll_calculation_run_id')->constrained()->restrictOnDelete();
            $table->json('snapshot');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['payroll_batch_id', 'revision']);
            $table->unique(['payroll_batch_id', 'batch_version']);
        });
        Schema::create('payroll_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_submission_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('batch_version');
            $table->string('action', 20);
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('actor_username', 50);
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['payroll_batch_id', 'batch_version']);
        });
        Schema::create('payroll_finalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_batch_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payroll_submission_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('snapshot');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Do not collapse a retained workflow into a draft during rollback.
        if (DB::table('payroll_batches')->where('status', '!=', 'draft')->exists() || DB::table('payroll_submissions')->exists()) {
            throw new LogicException('Retained payroll workflow records prevent rollback.');
        }
        Schema::dropIfExists('payroll_finalizations');
        Schema::dropIfExists('payroll_workflow_events');
        Schema::dropIfExists('payroll_submissions');
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->enum('status', ['draft'])->default('draft')->change();
            $table->dropColumn('coverage_preparer_ids');
        });
    }
};
