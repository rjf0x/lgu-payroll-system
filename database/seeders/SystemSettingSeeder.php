<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (SystemSetting::DEFAULTS as $key => $value) {
            SystemSetting::firstOrCreate(['setting_key' => $key], ['value' => $value]);
        }
    }
}
