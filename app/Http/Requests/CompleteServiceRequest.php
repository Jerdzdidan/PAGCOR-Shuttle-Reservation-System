<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CompleteServiceRequest extends FormRequest
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
            'opening_odometer_km' => ['required', 'numeric', 'min:0', 'max:99999999999.9', 'decimal:0,1'],
            'closing_odometer_km' => ['required', 'numeric', 'min:0', 'max:99999999999.9', 'decimal:0,1'],
            'actual_departure_at' => ['nullable', 'date'],
            'actual_arrival_at' => ['nullable', 'date'],
            'operational_notes' => ['nullable', 'string', 'max:5000'],
            'incident_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    is_numeric($this->input('opening_odometer_km'))
                    && is_numeric($this->input('closing_odometer_km'))
                    && (float) $this->input('closing_odometer_km')
                        < (float) $this->input('opening_odometer_km')
                ) {
                    $validator->errors()->add(
                        'closing_odometer_km',
                        'The closing odometer must be at least the opening odometer.'
                    );
                }

                if (
                    $this->filled('actual_departure_at')
                    && $this->filled('actual_arrival_at')
                    && strtotime((string) $this->input('actual_arrival_at'))
                        <= strtotime((string) $this->input('actual_departure_at'))
                ) {
                    $validator->errors()->add(
                        'actual_arrival_at',
                        'The actual arrival must be after the actual departure.'
                    );
                }
            },
        ];
    }
}
