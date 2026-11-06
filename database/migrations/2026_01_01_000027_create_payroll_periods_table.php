<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id('payroll_period_id');
            $table->string('period_name', 100);
            $table->string('payroll_type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date');
            $table->string('status', 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
