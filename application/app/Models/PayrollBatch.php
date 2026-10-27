<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Guarded(['*'])]
class PayrollBatch extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $batch) {
            if ($batch->isDirty(['payroll_version', 'root_payroll_batch_id', 'correction_of_finalization_id'])) {
                throw new LogicException('Payroll correction lineage is immutable.');
            }
            if ($batch->correction_of_finalization_id !== null && $batch->isDirty(['period_start', 'is_demonstration'])) {
                throw new LogicException('A correction must retain its payroll month and data use.');
            }
            if ($batch->getRawOriginal('status') === 'finalized' || self::whereKey($batch->id)->where('status', 'finalized')->exists()) {
                throw new LogicException('Finalized payroll batches are locked.');
            }
        });
        static::deleting(function (self $batch) {
            if ($batch->correction_of_finalization_id !== null) {
                throw new LogicException('Correction drafts and their authorization history must be retained.');
            }
            if (self::whereKey($batch->id)->where('status', '!=', 'draft')->exists()) {
                throw new LogicException('Payroll workflow history must be retained.');
            }
        });
    }

    protected function casts(): array
    {
        return ['period_start' => 'date', 'is_demonstration' => 'boolean', 'record_version' => 'integer', 'coverage_preparer_ids' => 'array', 'payroll_version' => 'integer'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }
}
