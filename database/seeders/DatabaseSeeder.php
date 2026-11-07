<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Demo seeding is disabled in production. Use ProductionDatabaseSeeder for initial setup.');
        }

        $this->call([
            RolePermissionSeeder::class,
            SystemSettingSeeder::class,
            EmploymentTypeSeeder::class,
            LeaveTypeSeeder::class,
            EarningTypeSeeder::class,
            DeductionTypeSeeder::class,
            StatutoryConfigSeeder::class,
            UserAccountSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
