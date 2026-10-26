<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_calculation_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->json('inputs');
            $table->json('parameters');
            $table->json('result');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['payroll_calculation_item_id', 'revision'], 'statutory_preview_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_previews');
    }
};
