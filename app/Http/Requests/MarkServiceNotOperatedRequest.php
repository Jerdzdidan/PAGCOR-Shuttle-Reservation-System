<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarkServiceNotOperatedRequest extends FormRequest
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
            'not_operated_reason' => ['required', 'string', 'max:5000'],
            'operational_notes' => ['nullable', 'string', 'max:5000'],
            'incident_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
