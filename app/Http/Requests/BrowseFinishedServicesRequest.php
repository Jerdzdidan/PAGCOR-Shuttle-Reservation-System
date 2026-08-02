<?php

namespace App\Http\Requests;

use App\Enums\ServiceOccurrenceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BrowseFinishedServicesRequest extends FormRequest
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
            'view' => ['nullable', Rule::in(['needs_completion', 'history'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'route_id' => ['nullable', 'integer', 'exists:shuttle_routes,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'outcome' => [
                'nullable',
                Rule::in([
                    ServiceOccurrenceStatus::Completed->value,
                    ServiceOccurrenceStatus::NotOperated->value,
                ]),
            ],
            'occurrence' => ['nullable', 'integer', 'exists:shuttle_service_occurrences,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->filled('date_from')
                    && $this->filled('date_to')
                    && (string) $this->input('date_to') < (string) $this->input('date_from')
                ) {
                    $validator->errors()->add(
                        'date_to',
                        'The ending date must be on or after the starting date.'
                    );
                }
            },
        ];
    }
}
