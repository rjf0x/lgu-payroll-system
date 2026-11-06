<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id('leave_type_id');
            $table->string('leave_name', 100);
            $table->text('description')->nullable();
            $table->decimal('default_credit', 8, 2);
            $table->boolean('is_paid');
            $table->string('status', 20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
