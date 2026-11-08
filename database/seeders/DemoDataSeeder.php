<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed fictional organization, salary, employee, and compensation data.
     */
    public function run(): void
    {
        $this->call(PayrollMasterDataSeeder::class);
    }
}
