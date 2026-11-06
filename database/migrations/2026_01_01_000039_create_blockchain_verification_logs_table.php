<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockchain_verification_logs', function (Blueprint $table) {
            $table->id('verification_id');
            $table->foreignId('anchor_id')->constrained('blockchain_anchors', 'anchor_id')->cascadeOnDelete();
            $table->foreignId('verified_by')->constrained('users', 'user_id');
            $table->char('stored_hash', 64);
            $table->char('calculated_hash', 64);
            $table->string('verification_result', 30);
            $table->text('remarks')->nullable();
            $table->dateTime('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockchain_verification_logs');
    }
};
