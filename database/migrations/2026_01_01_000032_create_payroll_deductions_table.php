<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_deductions', function (Blueprint $table) {
            $table->id('payroll_deduction_id');
            $table->foreignId('payroll_detail_id')->constrained('payroll_details', 'payroll_detail_id')->cascadeOnDelete();
            $table->foreignId('deduction_type_id')->constrained('deduction_types', 'deduction_type_id');
            $table->string('deduction_name_snapshot', 150);
            $table->decimal('amount', 15, 2);
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_deductions');
    }
};
