<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id('position_id');
            $table->foreignId('department_id')->constrained('departments', 'department_id');
            $table->string('position_code', 50)->unique();
            $table->string('position_title', 150);
            $table->unsignedInteger('salary_grade')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index('salary_grade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
