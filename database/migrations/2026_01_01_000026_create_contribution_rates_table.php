<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_rates', function (Blueprint $table) {
            $table->id('contribution_rate_id');
            $table->string('contribution_type', 100);
            $table->decimal('minimum_compensation', 15, 2);
            $table->decimal('maximum_compensation', 15, 2)->nullable();
            $table->decimal('employee_rate', 8, 4);
            $table->decimal('employer_rate', 8, 4);
            $table->decimal('employee_fixed_amount', 15, 2)->nullable();
            $table->decimal('employer_fixed_amount', 15, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_rates');
    }
};
