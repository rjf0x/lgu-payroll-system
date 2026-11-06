<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id('adjustment_id');
            $table->foreignId('payroll_detail_id')->constrained('payroll_details', 'payroll_detail_id')->cascadeOnDelete();
            $table->string('adjustment_type', 30);
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users', 'user_id');
            $table->foreignId('approved_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('approval_status', 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
