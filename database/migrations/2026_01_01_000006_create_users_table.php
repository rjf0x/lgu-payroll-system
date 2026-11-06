<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->foreignId('employee_id')->nullable()->unique()
                ->constrained('employees', 'employee_id')->nullOnDelete();
            $table->string('username', 100)->unique();
            $table->string('email', 150)->nullable();
            $table->string('password_hash', 255);
            $table->string('account_status', 20)->default('ACTIVE');
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
