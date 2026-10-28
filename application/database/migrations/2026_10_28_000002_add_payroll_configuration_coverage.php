<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_rule_versions', function (Blueprint $table) {
            $table->dropUnique('payroll_rule_activation_unique');
            $table->enum('personnel_classification', ['permanent', 'coterminous', 'part_time'])->nullable()->change();
            $table->string('department', 150)->nullable();
            $table->char('coverage_key', 64)->nullable();
        });
        foreach (['permanent', 'coterminous', 'part_time'] as $category) {
            DB::table('payroll_rule_versions')->where('personnel_classification', $category)
                ->update(['coverage_key' => hash('sha256', json_encode([null, $category], JSON_THROW_ON_ERROR))]);
        }
        Schema::table('payroll_rule_versions', function (Blueprint $table) {
            $table->char('coverage_key', 64)->nullable(false)->change();
            $table->unique(['coverage_key', 'is_demonstration', 'effective_from', 'activation_slot'], 'payroll_rule_activation_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('payroll_rule_versions')->whereNotNull('department')->exists()
            || DB::table('payroll_rule_versions')->whereNull('personnel_classification')->exists()) {
            throw new LogicException('Retained shared or department payroll configurations prevent rollback.');
        }
        Schema::table('payroll_rule_versions', function (Blueprint $table) {
            $table->dropUnique('payroll_rule_activation_unique');
            $table->dropColumn(['department', 'coverage_key']);
            $table->enum('personnel_classification', ['permanent', 'coterminous', 'part_time'])->nullable(false)->change();
            $table->unique(['personnel_classification', 'is_demonstration', 'effective_from', 'activation_slot'], 'payroll_rule_activation_unique');
        });
    }
};
