<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Newly created vehicles are always active; the status is only editable on update.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['status' => 'ACTIVE']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plate_number' => ['required', 'string', 'max:30', 'unique:vehicles,plate_number'],
            'vehicle_type' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'status' => ['required', 'in:ACTIVE,MAINTENANCE,INACTIVE'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
