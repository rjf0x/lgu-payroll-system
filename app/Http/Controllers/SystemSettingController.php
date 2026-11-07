<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemSettingRequest;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(): View
    {
        return view('system-settings.edit', ['settings' => SystemSetting::currentValues()]);
    }

    public function update(SystemSettingRequest $request, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit): void {
            $oldValues = SystemSetting::whereIn('setting_key', array_keys(SystemSetting::DEFAULTS))->orderBy('setting_key')->lockForUpdate()->pluck('value', 'setting_key')->all();
            $values = $request->validated('settings') + ['support_email' => null];
            foreach ($values as $key => $value) {
                SystemSetting::updateOrCreate(['setting_key' => $key], ['value' => $value]);
            }
            $audit->log('SETTINGS_UPDATED', 'System Settings', (int) $request->user()->getAuthIdentifier(), 'system_settings', oldValues: $oldValues, newValues: $values);
        });

        return redirect()->route('system-settings.edit')->with('status', 'System settings saved.');
    }
}
