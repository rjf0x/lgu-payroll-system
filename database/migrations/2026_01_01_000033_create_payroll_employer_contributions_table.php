<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_employer_contributions', function (Blueprint $table) {
            $table->id('employer_contribution_id');
            $table->foreignId('payroll_detail_id')->constrained('payroll_details', 'payroll_detail_id')->cascadeOnDelete();
            $table->foreignId('contribution_rate_id')->constrained('contribution_rates', 'contribution_rate_id');
            $table->string('contribution_type_snapshot', 100);
            $table->decimal('amount', 15, 2);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_employer_contributions');
    }
};
