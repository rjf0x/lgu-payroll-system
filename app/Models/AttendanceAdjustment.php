<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'attendance_id',
    'requested_by',
    'reviewed_by',
    'old_attendance_status',
    'requested_attendance_status',
    'old_credited_days',
    'requested_credited_days',
    'adjustment_reason',
    'approval_status',
    'requested_at',
    'reviewed_at',
])]
class AttendanceAdjustment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'adjustment_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_credited_days' => 'decimal:2',
            'requested_credited_days' => 'decimal:2',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_id', 'attendance_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }
}
