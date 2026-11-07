<?php

namespace Database\Factories;

use App\Models\TaxBracket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxBracket>
 */
class TaxBracketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tax_type' => 'ANNUAL', 'pay_frequency' => 'ANNUAL', 'minimum_income' => 0, 'maximum_income' => null,
            'base_tax' => 0, 'marginal_tax_rate' => 0, 'effective_from' => '2024-01-01', 'effective_to' => null,
        ];
    }
}
