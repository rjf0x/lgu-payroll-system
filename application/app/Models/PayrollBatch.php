<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['*'])]
class PayrollBatch extends Model
{
    protected function casts(): array
    {
        return ['period_start' => 'date', 'is_demonstration' => 'boolean', 'record_version' => 'integer'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }
}
