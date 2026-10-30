<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('besu_signer_nonces', function (Blueprint $table) {
            $table->string('scope', 64)->primary();
            $table->unsignedBigInteger('next_nonce')->default(0);
        });
        Schema::create('besu_anchor_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_commitment_id')->unique()->constrained()->restrictOnDelete();
            $table->char('reference', 66)->unique();
            $table->string('status', 20)->default('queued')->index();
            $table->json('identity')->nullable();
            $table->unsignedBigInteger('nonce')->nullable();
            $table->char('transaction_hash', 66)->nullable()->unique();
            // A signed transaction is a bearer authorization: encrypt at rest and never log it.
            $table->text('encrypted_transaction')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->char('block_hash', 66)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('besu_anchor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbox_id')->constrained('besu_anchor_outbox')->restrictOnDelete();
            $table->string('event', 40);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (DB::table('besu_anchor_outbox')->exists()) {
            throw new LogicException('Retained blockchain journals prevent rollback.');
        }
        Schema::dropIfExists('besu_anchor_events');
        Schema::dropIfExists('besu_anchor_outbox');
        Schema::dropIfExists('besu_signer_nonces');
    }
};
