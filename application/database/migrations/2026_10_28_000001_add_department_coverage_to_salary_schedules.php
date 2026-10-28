<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_schedules', function (Blueprint $table) {
            // Preserve existing authorized classification coverage as legacy records.
            $table->enum('personnel_classification', ['permanent', 'coterminous', 'part_time'])->nullable()->change();
            $table->string('department', 150)->nullable();
            $table->char('coverage_key', 64)->nullable();
            $table->unique(['coverage_key', 'effective_from', 'is_demonstration'], 'salary_department_date_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('salary_schedules')->whereNotNull('coverage_key')->exists()
            || DB::table('salary_schedules')->whereNull('personnel_classification')->exists()) {
            throw new LogicException('Retained department salary schedules prevent rollback.');
        }
        Schema::table('salary_schedules', function (Blueprint $table) {
            $table->dropUnique('salary_department_date_unique');
            $table->dropColumn(['department', 'coverage_key']);
            $table->enum('personnel_classification', ['permanent', 'coterminous', 'part_time'])->nullable(false)->change();
        });
    }
};
