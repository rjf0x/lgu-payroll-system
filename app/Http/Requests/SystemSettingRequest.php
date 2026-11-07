<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SystemSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array:system_name,organization_name,support_email'],
            'settings.system_name' => ['required', 'string', 'max:150'],
            'settings.organization_name' => ['required', 'string', 'max:150'],
            'settings.support_email' => ['nullable', 'email', 'max:150'],
        ];
    }
}
