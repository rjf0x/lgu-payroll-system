<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_detail_id',
    'earning_type_id',
    'earning_name_snapshot',
    'quantity',
    'rate',
    'amount',
    'is_taxable',
    'remarks',
])]
class PayrollEarning extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'payroll_earning_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_taxable' => 'boolean',
        ];
    }

    public function payrollDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollDetail::class, 'payroll_detail_id', 'payroll_detail_id');
    }

    public function earningType(): BelongsTo
    {
        return $this->belongsTo(EarningType::class, 'earning_type_id', 'earning_type_id');
    }
}
