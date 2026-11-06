<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_types', function (Blueprint $table) {
            $table->id('employment_type_id');
            $table->string('employment_type_name', 100)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_types');
    }
};
