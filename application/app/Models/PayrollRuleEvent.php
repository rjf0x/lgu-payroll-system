<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

#[Guarded(['*'])]
class PayrollRuleEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'datetime'];
    }

    public function effectiveDate(): ?string
    {
        $date = $this->snapshot['effective_from'] ?? null;
        if (! is_string($date)) {
            return null;
        }
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) {
            return $date;
        }
        // Read legacy UTC serialization in the application timezone without
        // rewriting the preserved event snapshot.
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/', $date)) {
            try {
                return CarbonImmutable::parse($date)->setTimezone(config('app.timezone'))->format('Y-m-d');
            } catch (InvalidArgumentException) {
                return null;
            }
        }

        return null;
    }

    protected static function booted(): void
    {
        $reject = fn () => throw new LogicException('Configuration change events are immutable.');
        static::updating($reject);
        static::deleting($reject);
    }
}
