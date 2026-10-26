<?php

namespace App\Http\Requests;

use App\Models\PayrollRuleVersion;
use App\Rules\Utf8Text;
use Illuminate\Foundation\Http\FormRequest;

class ActivatePayrollRuleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PayrollRuleVersion::class);
    }

    public function rules(): array
    {
        return [
            'record_version' => ['required', 'integer', 'min:1'],
            'approval_reference' => ['required', 'string', new Utf8Text, 'max:255'],
            'validation_reference' => ['required', 'string', new Utf8Text, 'max:255'],
            'activation_reason' => ['required', 'string', new Utf8Text, 'max:500'],
            'authorization_confirmed' => ['required', 'accepted'],
            'client_validation_confirmed' => ['sometimes', 'accepted'],
        ];
    }
}
