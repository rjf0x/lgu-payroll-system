<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'leave_balance_id',
    'leave_application_id',
    'transaction_type',
    'days',
    'balance_after',
    'transaction_date',
    'created_by',
    'remarks',
])]
class LeaveTransaction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'leave_transaction_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class, 'leave_balance_id', 'leave_balance_id');
    }

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id', 'leave_application_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}
