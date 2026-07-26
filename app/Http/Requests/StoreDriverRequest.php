<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
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
        return ['name' => ['required', 'string', 'max:255'], 'employee_id' => ['required', 'string', 'max:100', 'unique:drivers,employee_id'], 'contact_number' => ['required', 'string', 'max:50'], 'license_number' => ['required', 'string', 'max:100', 'unique:drivers,license_number'], 'license_expires_at' => ['required', 'date', 'after_or_equal:today'], 'status' => ['required', 'in:ACTIVE,INACTIVE'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
