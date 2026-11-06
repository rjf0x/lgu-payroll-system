<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id('payslip_id');
            $table->foreignId('payroll_detail_id')->unique()
                ->constrained('payroll_details', 'payroll_detail_id')->cascadeOnDelete();
            $table->string('payslip_number', 100)->unique();
            $table->string('file_path', 500)->nullable();
            $table->dateTime('generated_at');
            $table->dateTime('published_at')->nullable();
            $table->string('publication_status', 30);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
