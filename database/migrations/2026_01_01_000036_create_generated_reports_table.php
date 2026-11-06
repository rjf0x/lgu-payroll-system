<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->string('report_type', 100);
            $table->foreignId('generated_by')->constrained('users', 'user_id');
            $table->json('filters')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->dateTime('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
