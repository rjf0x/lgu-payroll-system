<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_snapshot_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_finalization_id')->unique()->constrained()->restrictOnDelete();
            $table->string('schema_version', 50);
            $table->string('canonicalization', 50);
            $table->string('algorithm', 10);
            // Valid UTF-8 canonical JSON, without a JSON cast/re-encoding by storage.
            $table->longText('canonical_bytes');
            $table->char('sha256', 64);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (DB::table('payroll_snapshot_archives')->exists()) {
            throw new LogicException('Retained snapshot bytes prevent rollback.');
        }
        Schema::dropIfExists('payroll_snapshot_archives');
    }
};
