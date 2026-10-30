<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_verifications', function (Blueprint $table) {
            $table->char('besu_anchor_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('payroll_verifications')->whereNotNull('besu_anchor_fingerprint')->exists()) {
            throw new LogicException('Retained Besu verification context prevents rollback.');
        }
        Schema::table('payroll_verifications', function (Blueprint $table) {
            $table->dropColumn('besu_anchor_fingerprint');
        });
    }
};
