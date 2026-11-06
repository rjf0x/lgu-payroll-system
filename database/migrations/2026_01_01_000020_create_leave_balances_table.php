<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id('leave_balance_id');
            $table->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types', 'leave_type_id');
            $table->year('leave_year');
            $table->decimal('opening_balance', 8, 2)->default(0.00);
            $table->decimal('total_credits', 8, 2)->default(0.00);
            $table->decimal('total_used', 8, 2)->default(0.00);
            $table->decimal('available_balance', 8, 2)->default(0.00);
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['employee_id', 'leave_type_id', 'leave_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
