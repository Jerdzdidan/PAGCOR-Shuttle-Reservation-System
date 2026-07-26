<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
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
        return ['plate_number' => ['required', 'string', 'max:30', Rule::unique(Vehicle::class)->ignore($this->route('vehicle'))], 'vehicle_type' => ['required', 'string', 'max:100'], 'capacity' => ['required', 'integer', 'min:1', 'max:1000'], 'status' => ['required', 'in:ACTIVE,MAINTENANCE,INACTIVE'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
