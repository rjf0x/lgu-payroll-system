<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id('tax_bracket_id');
            $table->string('tax_type', 50);
            $table->string('pay_frequency', 30);
            $table->decimal('minimum_income', 15, 2);
            $table->decimal('maximum_income', 15, 2)->nullable();
            $table->decimal('base_tax', 15, 2);
            $table->decimal('marginal_tax_rate', 8, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
    }
};
