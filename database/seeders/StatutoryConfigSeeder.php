<?php

namespace Database\Seeders;

use App\Models\ContributionRate;
use App\Models\TaxBracket;
use Illuminate\Database\Seeder;

class StatutoryConfigSeeder extends Seeder
{
    /**
     * Seed illustrative tax brackets and contribution rates.
     *
     * These amounts are placeholders for the mock-defense build and MUST be
     * replaced with the LGU's official, current figures before production use.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Illustrative statutory rates cannot be seeded in production.');
        }

        $effectiveFrom = '2024-01-01';

        $brackets = [
            ['minimum' => 0, 'maximum' => 250000, 'base_tax' => 0, 'rate' => 0.0000],
            ['minimum' => 250000, 'maximum' => 400000, 'base_tax' => 0, 'rate' => 0.1500],
            ['minimum' => 400000, 'maximum' => 800000, 'base_tax' => 22500, 'rate' => 0.2000],
            ['minimum' => 800000, 'maximum' => 2000000, 'base_tax' => 102500, 'rate' => 0.2500],
            ['minimum' => 2000000, 'maximum' => 8000000, 'base_tax' => 402500, 'rate' => 0.3000],
            ['minimum' => 8000000, 'maximum' => null, 'base_tax' => 2202500, 'rate' => 0.3500],
        ];

        foreach ($brackets as $bracket) {
            TaxBracket::whereDate('effective_from', $effectiveFrom)->firstOrCreate(
                [
                    'tax_type' => 'ANNUAL',
                    'pay_frequency' => 'ANNUAL',
                    'minimum_income' => $bracket['minimum'],
                ],
                [
                    'effective_from' => $effectiveFrom,
                    'maximum_income' => $bracket['maximum'],
                    'base_tax' => $bracket['base_tax'],
                    'marginal_tax_rate' => $bracket['rate'],
                ],
            );
        }

        $rates = [
            ['type' => ContributionRate::TYPE_GSIS, 'minimum' => 0, 'maximum' => null, 'employee' => 0.0900, 'employer' => 0.1200],
            ['type' => ContributionRate::TYPE_PHILHEALTH, 'minimum' => 10000, 'maximum' => 100000, 'employee' => 0.0250, 'employer' => 0.0250],
            ['type' => ContributionRate::TYPE_PAGIBIG, 'minimum' => 0, 'maximum' => 10000, 'employee' => 0.0200, 'employer' => 0.0200],
        ];

        foreach ($rates as $rate) {
            ContributionRate::whereDate('effective_from', $effectiveFrom)->firstOrCreate(
                [
                    'contribution_type' => $rate['type'],
                ],
                [
                    'effective_from' => $effectiveFrom,
                    'minimum_compensation' => $rate['minimum'],
                    'maximum_compensation' => $rate['maximum'],
                    'employee_rate' => $rate['employee'],
                    'employer_rate' => $rate['employer'],
                ],
            );
        }
    }
}
