<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensation_profiles', function (Blueprint $table) {
            $table->id('compensation_id');
            $table->foreignId('employment_id')->constrained('employment_records', 'employment_id')->cascadeOnDelete();
            $table->foreignId('grade_step_id')->nullable()
                ->constrained('salary_grade_steps', 'grade_step_id')->nullOnDelete();
            $table->string('pay_basis', 30);
            $table->decimal('basic_rate', 15, 2)->nullable();
            $table->decimal('service_fraction', 5, 4)->default(1.0000);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['employment_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensation_profiles');
    }
};
