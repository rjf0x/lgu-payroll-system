<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_earning_assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->foreignId('employment_id')->constrained('employment_records', 'employment_id')->cascadeOnDelete();
            $table->foreignId('earning_type_id')->constrained('earning_types', 'earning_type_id');
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->string('frequency', 30);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_earning_assignments');
    }
};
