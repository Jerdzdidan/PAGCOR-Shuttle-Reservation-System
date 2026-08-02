<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:employees,email'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:100', Rule::exists(Department::class, 'name')],
            'position' => ['nullable', 'string', 'max:100'],
            'priority_status' => ['required', 'in:REGULAR,PRIORITY'],
            'status' => ['required', 'in:ACTIVE,INACTIVE'],
        ];
    }
}
