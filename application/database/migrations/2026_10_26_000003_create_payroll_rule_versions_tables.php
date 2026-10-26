<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_key')->unique();
            $table->string('creation_hash', 64);
            $table->string('name', 150);
            $table->enum('personnel_classification', ['permanent', 'coterminous', 'part_time']);
            $table->boolean('is_demonstration');
            $table->date('effective_from');
            $table->unsignedInteger('record_version')->default(1);
            $table->json('parameters');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->unsignedTinyInteger('activation_slot')->nullable();
            $table->string('approval_reference', 255)->nullable();
            $table->string('validation_reference', 255)->nullable();
            $table->timestamps();
            // Multiple drafts are allowed; only one locked version per scope/date.
            $table->unique(['personnel_classification', 'is_demonstration', 'effective_from', 'activation_slot'], 'payroll_rule_activation_unique');
        });
        Schema::create('payroll_rule_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_rule_version_id')->constrained()->restrictOnDelete();
            $table->enum('action', ['created', 'revised', 'activated']);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_username', 50);
            $table->string('reason', 500);
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['payroll_rule_version_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_rule_events');
        Schema::dropIfExists('payroll_rule_versions');
    }
};
