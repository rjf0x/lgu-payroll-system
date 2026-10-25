<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Rules\SupportedPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['required', 'boolean'],
            'password' => ['bail', $this->isMethod('post') ? 'required' : 'nullable', 'string', new SupportedPassword, 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->route('user')?->is($this->user())
                && ($this->input('role') !== Role::Administrator->value || ! $this->boolean('is_active'))) {
                $validator->errors()->add('role', 'You cannot deactivate your own account or change your own administrator role.');
            }
        }];
    }
}
