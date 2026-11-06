<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'payroll_run_id',
    'employee_id',
    'employment_id',
    'employee_number_snapshot',
    'employee_name_snapshot',
    'department_snapshot',
    'position_snapshot',
    'salary_grade_snapshot',
    'salary_step_snapshot',
    'salary_rate_snapshot',
    'pay_basis_snapshot',
    'basic_pay',
    'total_earnings',
    'gross_pay',
    'total_deductions',
    'nthp_before_lbp',
    'lbp_loan_deduction',
    'net_pay',
    'days_worked',
    'days_present',
    'days_absent',
    'days_on_leave',
    'payroll_status',
])]
class PayrollDetail extends Model
{
    use HasFactory;

    protected $primaryKey = 'payroll_detail_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary_rate_snapshot' => 'decimal:2',
            'basic_pay' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'nthp_before_lbp' => 'decimal:2',
            'lbp_loan_deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'days_worked' => 'decimal:2',
            'days_present' => 'decimal:2',
            'days_absent' => 'decimal:2',
            'days_on_leave' => 'decimal:2',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function employmentRecord(): BelongsTo
    {
        return $this->belongsTo(EmploymentRecord::class, 'employment_id', 'employment_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(PayrollEarning::class, 'payroll_detail_id', 'payroll_detail_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class, 'payroll_detail_id', 'payroll_detail_id');
    }

    public function employerContributions(): HasMany
    {
        return $this->hasMany(PayrollEmployerContribution::class, 'payroll_detail_id', 'payroll_detail_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class, 'payroll_detail_id', 'payroll_detail_id');
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class, 'payroll_detail_id', 'payroll_detail_id');
    }
}
