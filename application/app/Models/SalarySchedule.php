<?php

namespace App\Models;

use App\Enums\PersonnelClassification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use LogicException;

#[Fillable(['name', 'personnel_classification', 'effective_from', 'is_demonstration', 'created_by'])]
class SalarySchedule extends Model
{
    public static function coverageKey(?string $department): string
    {
        return hash('sha256', $department === null ? 'all' : 'department:'.mb_strtolower(trim($department)));
    }

    public static function departmentOptions(): Collection
    {
        // Use the same normalization as coverage keys, independent of database accent collations.
        return Employee::query()->pluck('office')->merge(self::whereNotNull('department')->pluck('department'))
            ->filter(fn ($name) => is_string($name) && mb_check_encoding($name, 'UTF-8') && trim($name) !== '')
            ->map(fn ($name) => trim($name))->unique(fn ($name) => mb_strtolower($name))->sort()->values();
    }

    protected function coverageLabel(): Attribute
    {
        return Attribute::get(fn () => $this->coverage_key !== null
            ? ($this->department ?? 'All departments')
            : 'Legacy classification: '.($this->personnel_classification?->label() ?? 'Unknown'));
    }

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
