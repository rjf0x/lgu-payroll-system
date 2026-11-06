<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id('deduction_type_id');
            $table->string('deduction_name', 150);
            $table->string('deduction_category', 100);
            $table->string('computation_type', 50);
            $table->boolean('is_statutory');
            $table->boolean('is_pre_tax');
            $table->string('status', 20);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_types');
    }
};
