<?php

namespace App\Http\Requests;

use App\Rules\Utf8Text;
use Illuminate\Foundation\Http\FormRequest;

class PayrollWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = match ($this->route('action')) {
            'submit' => 'submit', 'approve', 'return' => 'review', 'finalize' => 'finalize', default => null,
        };

        return $ability !== null && $this->user()->can($ability, $this->route('batch'));
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('action'));
    }

    public static function rulesFor(string $action): array
    {
        return [
            'record_version' => ['required', 'integer', 'min:1'],
            'submission_id' => [$action === 'submit' ? 'nullable' : 'required', 'integer', 'min:1'],
            'review_token' => [$action === 'submit' ? 'required' : 'nullable', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'reason' => ['required', 'string', new Utf8Text, 'max:500'],
            'review_confirmed' => ['required', 'accepted'],
        ];
    }
}
