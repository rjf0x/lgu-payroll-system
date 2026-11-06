<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_history', function (Blueprint $table) {
            $table->id('history_id');
            $table->foreignId('employment_id')->constrained('employment_records', 'employment_id')->cascadeOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments', 'department_id')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments', 'department_id')->nullOnDelete();
            $table->foreignId('from_position_id')->nullable()->constrained('positions', 'position_id')->nullOnDelete();
            $table->foreignId('to_position_id')->nullable()->constrained('positions', 'position_id')->nullOnDelete();
            $table->string('change_type', 50);
            $table->date('effective_date');
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users', 'user_id');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_history');
    }
};
