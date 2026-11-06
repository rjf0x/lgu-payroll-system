<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_earnings', function (Blueprint $table) {
            $table->id('payroll_earning_id');
            $table->foreignId('payroll_detail_id')->constrained('payroll_details', 'payroll_detail_id')->cascadeOnDelete();
            $table->foreignId('earning_type_id')->constrained('earning_types', 'earning_type_id');
            $table->string('earning_name_snapshot', 150);
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('rate', 15, 2)->nullable();
            $table->decimal('amount', 15, 2);
            $table->boolean('is_taxable');
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_earnings');
    }
};
