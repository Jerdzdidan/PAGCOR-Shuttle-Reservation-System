<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBookingWindowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'opens_at' => ['required_if_accepted:enabled', 'nullable', 'date_format:H:i'],
            'closes_at' => ['required_if_accepted:enabled', 'nullable', 'date_format:H:i'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('enabled') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('opens_at') === $this->input('closes_at')) {
                    $validator->errors()->add(
                        'closes_at',
                        'The closing time must be different from the opening time.'
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'opens_at.required_if_accepted' => 'Set the time employees may start booking.',
            'closes_at.required_if_accepted' => 'Set the time employee booking closes.',
        ];
    }
}
