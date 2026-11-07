<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Use ProductionDatabaseSeeder to create the initial production administrator.');
        }

        User::firstOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('ChangeMe123!'),
            ]
        );
    }
}
