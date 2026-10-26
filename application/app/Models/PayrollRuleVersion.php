<?php

namespace App\Models;

use App\Enums\PersonnelClassification;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class PayrollRuleVersion extends Model
{
    protected function casts(): array
    {
        return ['personnel_classification' => PersonnelClassification::class, 'is_demonstration' => 'boolean',
            'effective_from' => 'date', 'record_version' => 'integer', 'parameters' => 'array', 'activated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            if ($version->getRawOriginal('activated_at') !== null) {
                throw new LogicException('Activated payroll rule versions are locked. Create a new dated version.');
            }
        });
        static::deleting(fn () => throw new LogicException('Payroll rule versions are retained for history.'));
    }
}
