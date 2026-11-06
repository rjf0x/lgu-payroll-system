<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->user_id : null;
        $isCreate = $userId === null;

        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,employee_id'],
            'username' => [
                'required', 'string', 'max:100',
                Rule::unique('users', 'username')->ignore($userId, 'user_id'),
            ],
            'email' => ['nullable', 'email', 'max:150'],
            'account_status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,role_id'],
        ];
    }
}
