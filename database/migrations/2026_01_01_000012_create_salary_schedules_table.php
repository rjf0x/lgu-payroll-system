<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_schedules', function (Blueprint $table) {
            $table->id('salary_schedule_id');
            $table->string('schedule_name', 150);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reference_document', 255)->nullable();
            $table->string('status', 20);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_schedules');
    }
};
