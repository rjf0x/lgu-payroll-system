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
            $table->foreignId('besu_anchor_id')->nullable()->constrained('besu_anchor_outbox')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('payroll_verifications')->whereNotNull('besu_anchor_id')->exists()) {
            throw new LogicException('Retained Besu verification history prevents rollback.');
        }
        Schema::table('payroll_verifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('besu_anchor_id');
        });
    }
};
