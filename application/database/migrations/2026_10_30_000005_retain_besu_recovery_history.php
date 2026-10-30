<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('besu_anchor_outbox', function (Blueprint $table) {
            $table->unsignedInteger('retry_count')->default(0);
            $table->string('last_error', 40)->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
        });
        Schema::create('besu_anchor_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbox_id')->constrained('besu_anchor_outbox')->restrictOnDelete();
            $table->char('transaction_hash', 66)->unique();
            $table->json('identity')->nullable();
            $table->unsignedBigInteger('nonce')->nullable();
            $table->text('encrypted_transaction')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('block_number')->nullable();
            $table->char('block_hash', 66)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        // Retain existing journals verbatim; never re-sign or expose encrypted payloads during upgrade.
        DB::table('besu_anchor_outbox')->whereNotNull('transaction_hash')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $fields = array_intersect_key((array) $row, array_flip(['transaction_hash', 'identity', 'nonce', 'encrypted_transaction', 'status', 'block_number', 'block_hash', 'confirmed_at', 'created_at', 'updated_at']));
                DB::table('besu_anchor_transactions')->insert(['outbox_id' => $row->id, ...$fields]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('besu_anchor_transactions')->exists() || DB::table('besu_anchor_outbox')->exists()) {
            throw new LogicException('Retained Besu recovery history prevents rollback.');
        }
        Schema::dropIfExists('besu_anchor_transactions');
        Schema::table('besu_anchor_outbox', function (Blueprint $table) {
            $table->dropIndex(['next_attempt_at']);
            $table->dropColumn(['retry_count', 'last_error', 'next_attempt_at']);
        });
    }
};
