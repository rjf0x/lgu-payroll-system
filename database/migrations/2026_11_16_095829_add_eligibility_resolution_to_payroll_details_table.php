<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->text('eligibility_reason')->nullable();
            $table->foreignId('eligibility_resolved_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('eligibility_resolved_at')->nullable();
            $table->json('eligibility_context')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eligibility_resolved_by');
            $table->dropColumn(['eligibility_reason', 'eligibility_resolved_at', 'eligibility_context']);
        });
    }
};
