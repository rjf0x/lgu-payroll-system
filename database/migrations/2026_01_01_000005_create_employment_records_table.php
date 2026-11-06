<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_records', function (Blueprint $table) {
            $table->id('employment_id');
            $table->foreignId('employee_id')->constrained('employees', 'employee_id');
            $table->foreignId('department_id')->constrained('departments', 'department_id');
            $table->foreignId('position_id')->constrained('positions', 'position_id');
            $table->foreignId('employment_type_id')->constrained('employment_types', 'employment_type_id');
            $table->string('appointment_number', 100)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('employment_status', 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_records');
    }
};
