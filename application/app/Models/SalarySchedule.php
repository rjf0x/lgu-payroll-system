<?php

namespace App\Models;

use App\Enums\PersonnelClassification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['name', 'personnel_classification', 'effective_from', 'is_demonstration', 'created_by'])]
class SalarySchedule extends Model
{
    protected function casts(): array
    {
        return ['personnel_classification' => PersonnelClassification::class, 'effective_from' => 'date', 'is_demonstration' => 'boolean', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function ($schedule) {
            if ($schedule->getRawOriginal('published_at') !== null) {
                throw new LogicException('Published salary schedules are immutable.');
            }
        });
        static::deleting(function ($schedule) {
            if ($schedule->published_at !== null) {
                throw new LogicException('Published salary schedules cannot be deleted.');
            }
        });
    }

    public function rates(): HasMany
    {
        return $this->hasMany(SalaryRate::class);
    }
}
