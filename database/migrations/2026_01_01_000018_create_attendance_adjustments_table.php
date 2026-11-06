<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id('adjustment_id');
            $table->foreignId('attendance_id')->constrained('attendance_records', 'attendance_id')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users', 'user_id');
            $table->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('old_attendance_status', 30);
            $table->string('requested_attendance_status', 30);
            $table->decimal('old_credited_days', 4, 2);
            $table->decimal('requested_credited_days', 4, 2);
            $table->text('adjustment_reason');
            $table->string('approval_status', 30);
            $table->dateTime('requested_at');
            $table->dateTime('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_adjustments');
    }
};
