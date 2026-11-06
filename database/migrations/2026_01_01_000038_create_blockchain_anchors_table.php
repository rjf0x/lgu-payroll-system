<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockchain_anchors', function (Blueprint $table) {
            $table->id('anchor_id');
            $table->foreignId('payroll_run_id')->unique()
                ->constrained('payroll_runs', 'payroll_run_id')->cascadeOnDelete();
            $table->char('payload_hash', 64);
            $table->string('hash_algorithm', 30);
            $table->string('transaction_id', 255)->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->string('network_name', 100);
            $table->string('contract_address', 255)->nullable();
            $table->string('anchor_status', 30);
            $table->foreignId('anchored_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->dateTime('anchored_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockchain_anchors');
    }
};
