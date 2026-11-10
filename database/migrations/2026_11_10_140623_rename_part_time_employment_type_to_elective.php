<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('employment_types')->where('employment_type_name', 'Part-time')->update([
            'employment_type_name' => 'Elective',
            'description' => 'Elective position filled through election.',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('employment_types')->where('employment_type_name', 'Elective')->update([
            'employment_type_name' => 'Part-time',
            'description' => 'Part-time appointment using an approved daily or proportionate rate.',
        ]);
    }
};
