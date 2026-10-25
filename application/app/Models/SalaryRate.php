<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['grade', 'step', 'monthly_salary'])]
class SalaryRate extends Model
{
    protected function casts(): array
    {
        return ['grade' => 'integer', 'step' => 'integer', 'monthly_salary' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        $guard = function ($rate) {
            $scheduleIds = array_filter([$rate->getRawOriginal('salary_schedule_id'), $rate->salary_schedule_id]);
            if (SalarySchedule::whereIn('id', $scheduleIds)->whereNotNull('published_at')->exists()) {
                throw new LogicException('Published salary rates are immutable.');
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SalarySchedule::class, 'salary_schedule_id');
    }
}
