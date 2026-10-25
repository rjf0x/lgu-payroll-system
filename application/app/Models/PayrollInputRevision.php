<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Guarded(['*'])]
class PayrollInputRevision extends Model
{
    public $timestamps = false;

    public const GROUPS = ['allowances' => 'Allowances', 'bonuses' => 'Bonuses / additional earnings', 'loans' => 'Loan deductions', 'adjustments' => 'Manual adjustments'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'entry_snapshot' => 'array', 'revision' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Payroll input revisions are immutable. Save a new draft revision.');
        static::updating($reject);
        static::deleting($reject);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applicableAllowances(): array
    {
        return array_values(array_filter($this->payload['allowances'], fn ($row) => $row['is_applicable'] === true));
    }

    public function allowanceTotal(): string
    {
        $total = '0.00';
        foreach ($this->applicableAllowances() as $row) {
            $total = bcadd($total, $row['amount'], 2);
        }

        return $total;
    }
}
