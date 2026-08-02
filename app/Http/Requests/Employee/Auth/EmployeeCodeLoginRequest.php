<?php

namespace App\Http\Requests\Employee\Auth;

use App\Services\EmployeeIdentifier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeCodeLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_code' => ['required', 'string', 'regex:'.EmployeeIdentifier::PATTERN],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'employee_code' => trim((string) $this->input('employee_code')),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'employee_code.required' => 'Enter your employee ID.',
            'employee_code.regex' => 'Use the employee ID format YY-00000, such as 26-00001.',
        ];
    }
}
