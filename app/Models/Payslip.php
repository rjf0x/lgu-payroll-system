<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_detail_id',
    'payslip_number',
    'file_path',
    'generated_at',
    'published_at',
    'publication_status',
])]
class Payslip extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'payslip_id';

    public const STATUS_GENERATED = 'GENERATED';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const STATUS_WITHDRAWN = 'WITHDRAWN';

    public function isViewableFor(PayrollRun $run): bool
    {
        return in_array($run->status, [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_FINALIZED], true)
            && in_array($this->publication_status, [self::STATUS_GENERATED, self::STATUS_PUBLISHED], true);
    }

    public function isPublishableFor(PayrollRun $run): bool
    {
        return $run->status === PayrollRun::STATUS_FINALIZED && $this->publication_status === self::STATUS_GENERATED;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function payrollDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollDetail::class, 'payroll_detail_id', 'payroll_detail_id');
    }
}
