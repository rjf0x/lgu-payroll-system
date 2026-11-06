<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['employee_id', 'attendance_date', 'attendance_status', 'credited_days', 'source', 'remarks'])]
class AttendanceRecord extends Model
{
    use HasFactory;

    protected $primaryKey = 'attendance_id';

    public const STATUS_PRESENT = 'PRESENT';

    public const STATUS_HALF_DAY = 'HALF_DAY';

    public const STATUS_ABSENT = 'ABSENT';

    public const STATUS_ON_LEAVE = 'ON_LEAVE';

    public const STATUS_HOLIDAY = 'HOLIDAY';

    public const STATUS_REST_DAY = 'REST_DAY';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'credited_days' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class, 'attendance_id', 'attendance_id');
    }
}
