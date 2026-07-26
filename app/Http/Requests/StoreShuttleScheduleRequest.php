<?php

namespace App\Http\Requests;

use App\Services\ShuttleScheduleValidationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreShuttleScheduleRequest extends FormRequest
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
            'route_id' => ['required', 'integer', 'exists:shuttle_routes,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'direction' => ['required', 'in:OUTBOUND,RETURN'],
            'departure_time' => ['required', 'date_format:H:i'],
            'operating_days' => ['required', 'array', 'min:1'],
            'operating_days.*' => ['required', 'string', 'distinct', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'capacity_override' => ['nullable', 'integer', 'min:1'],
            'priority_seats' => ['nullable', 'array'],
            'priority_seats.*' => ['integer', 'distinct', 'min:1'],
            'unavailable_seats' => ['nullable', 'array'],
            'unavailable_seats.*' => ['integer', 'distinct', 'min:1'],
            'waitlist_enabled' => ['required', 'boolean'],
            'waitlist_capacity' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'status' => ['required', 'in:ACTIVE,INACTIVE'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(ShuttleScheduleValidationService $validationService): array
    {
        return [
            function (Validator $validator) use ($validationService): void {
                $validationService->validate($this, $validator);
            },
        ];
    }
}
