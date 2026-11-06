<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['leave_name', 'description', 'default_credit', 'is_paid', 'status'])]
class LeaveType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'leave_type_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_credit' => 'decimal:2',
            'is_paid' => 'boolean',
        ];
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'leave_type_id', 'leave_type_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class, 'leave_type_id', 'leave_type_id');
    }
}
