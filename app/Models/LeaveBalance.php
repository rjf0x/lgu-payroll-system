<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'leave_type_id',
    'leave_year',
    'opening_balance',
    'total_credits',
    'total_used',
    'available_balance',
])]
class LeaveBalance extends Model
{
    use HasFactory;

    public const CREATED_AT = null;

    protected $primaryKey = 'leave_balance_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'total_credits' => 'decimal:2',
            'total_used' => 'decimal:2',
            'available_balance' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id', 'leave_type_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveTransaction::class, 'leave_balance_id', 'leave_balance_id');
    }
}
