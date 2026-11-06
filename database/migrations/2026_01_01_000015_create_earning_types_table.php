<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earning_types', function (Blueprint $table) {
            $table->id('earning_type_id');
            $table->string('earning_name', 150);
            $table->string('earning_category', 100);
            $table->string('computation_type', 50);
            $table->boolean('is_taxable');
            $table->boolean('is_recurring');
            $table->string('status', 20);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earning_types');
    }
};
