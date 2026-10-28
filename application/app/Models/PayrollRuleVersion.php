<?php

namespace App\Models;

use App\Enums\PersonnelClassification;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;

#[Guarded(['*'])]
class PayrollRuleVersion extends Model
{
    public static function coverageKey(?string $department, ?string $category): string
    {
        return hash('sha256', json_encode([$department === null ? null : mb_strtolower(trim($department)), $category], JSON_THROW_ON_ERROR));
    }

    public static function departmentOptions(): Collection
    {
        return SalarySchedule::departmentOptions()->merge(self::whereNotNull('department')->pluck('department'))
            ->filter(fn ($name) => is_string($name) && mb_check_encoding($name, 'UTF-8') && trim($name) !== '')
            ->map(fn ($name) => trim($name))->unique(fn ($name) => mb_strtolower($name))->sort()->values();
    }

    protected function departmentLabel(): Attribute
    {
        return Attribute::get(fn () => $this->department ?? 'All departments');
    }

    protected function classificationLabel(): Attribute
    {
        return Attribute::get(fn () => $this->personnel_classification?->label() ?? 'All classifications');
    }

    protected function casts(): array
    {
        return ['personnel_classification' => PersonnelClassification::class, 'is_demonstration' => 'boolean',
            'effective_from' => 'date', 'record_version' => 'integer', 'parameters' => 'array', 'activated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version) {
            $version->coverage_key = self::coverageKey($version->department, $version->personnel_classification?->value);
        });
        static::updating(function (self $version) {
            if ($version->getRawOriginal('activated_at') !== null) {
                throw new LogicException('Activated payroll rule versions are locked. Create a new dated version.');
            }
            $version->coverage_key = self::coverageKey($version->department, $version->personnel_classification?->value);
        });
        static::deleting(fn () => throw new LogicException('Payroll rule versions are retained for history.'));
    }
}
