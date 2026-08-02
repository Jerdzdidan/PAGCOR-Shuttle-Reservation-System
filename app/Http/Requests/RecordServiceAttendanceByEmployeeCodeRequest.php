<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordServiceAttendanceByEmployeeCodeRequest extends FormRequest
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
            'employee_code' => ['bail', 'required', 'string', 'size:8', 'regex:/^\d{2}-\d{5}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'employee_code.size' => 'Enter an employee ID in YY-00000 format.',
            'employee_code.regex' => 'Enter an employee ID in YY-00000 format.',
        ];
    }
}
