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
