<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['employment_type_name', 'description', 'status'])]
class EmploymentType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'employment_type_id';

    public const PERMANENT = 'Permanent';

    public const COTERMINOUS = 'Coterminous';

    public const PART_TIME = 'Part-time';

    public function employmentRecords(): HasMany
    {
        return $this->hasMany(EmploymentRecord::class, 'employment_type_id', 'employment_type_id');
    }
}
