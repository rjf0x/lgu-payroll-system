<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['period_name', 'payroll_type', 'start_date', 'end_date', 'pay_date', 'status'])]
class PayrollPeriod extends Model
{
    use HasFactory;

    protected $primaryKey = 'payroll_period_id';

    public const TYPE_MONTHLY = 'MONTHLY';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'pay_date' => 'date',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'payroll_period_id', 'payroll_period_id');
    }
}
