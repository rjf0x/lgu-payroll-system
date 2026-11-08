<?php

namespace Database\Seeders;

use App\Models\EarningType;
use Illuminate\Database\Seeder;

class EarningTypeSeeder extends Seeder
{
    /**
     * Seed the manually entered allowance types from Payrolll.xlsx, Payroll!G3:L3.
     *
     * Tax classifications are initial placeholders and must be confirmed against
     * BIR rules and the Municipal Accounting Office before production use.
     */
    public function run(): void
    {
        $earnings = [
            ['earning_name' => 'PERA', 'is_taxable' => false],
            ['earning_name' => 'Hazard Pay', 'is_taxable' => false],
            ['earning_name' => 'Clothing Allowance', 'is_taxable' => false],
            ['earning_name' => 'Subsistence Allowance', 'is_taxable' => false],
            ['earning_name' => 'Laundry Allowance', 'is_taxable' => false],
            ['earning_name' => 'Medical Allowance', 'is_taxable' => false],
        ];

        foreach ($earnings as $earning) {
            EarningType::firstOrCreate(
                ['earning_name' => $earning['earning_name']],
                [
                    'earning_category' => 'ALLOWANCE',
                    'computation_type' => 'MANUAL',
                    'is_taxable' => $earning['is_taxable'],
                    'is_recurring' => true,
                    'status' => 'ACTIVE',
                ],
            );
        }
    }
}
