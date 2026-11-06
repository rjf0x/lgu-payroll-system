<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_transactions', function (Blueprint $table) {
            $table->id('leave_transaction_id');
            $table->foreignId('leave_balance_id')->constrained('leave_balances', 'leave_balance_id')->cascadeOnDelete();
            $table->foreignId('leave_application_id')->nullable()
                ->constrained('leave_applications', 'leave_application_id')->nullOnDelete();
            $table->string('transaction_type', 30);
            $table->decimal('days', 8, 2);
            $table->decimal('balance_after', 8, 2);
            $table->date('transaction_date');
            $table->foreignId('created_by')->constrained('users', 'user_id');
            $table->text('remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_transactions');
    }
};
