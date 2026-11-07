<?php

namespace App\Models;

use Database\Factories\SystemSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['setting_key', 'value'])]
class SystemSetting extends Model
{
    /** @use HasFactory<SystemSettingFactory> */
    use HasFactory;

    public const DEFAULTS = ['system_name' => 'LGU Payroll System', 'organization_name' => 'Local Government Unit', 'support_email' => null];

    /** @return array{system_name: string, organization_name: string, support_email: ?string} */
    public static function currentValues(): array
    {
        return array_replace(self::DEFAULTS, self::query()->whereIn('setting_key', array_keys(self::DEFAULTS))->pluck('value', 'setting_key')->all());
    }
}
