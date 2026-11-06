<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tax_type',
    'pay_frequency',
    'minimum_income',
    'maximum_income',
    'base_tax',
    'marginal_tax_rate',
    'effective_from',
    'effective_to',
])]
class TaxBracket extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'tax_bracket_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_income' => 'decimal:2',
            'maximum_income' => 'decimal:2',
            'base_tax' => 'decimal:2',
            'marginal_tax_rate' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
