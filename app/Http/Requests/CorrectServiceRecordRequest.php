<?php

namespace App\Http\Requests;

use App\Enums\ServiceAttendanceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CorrectServiceRecordRequest extends FormRequest
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
            'correction_reason' => ['required_without:reason', 'string', 'max:2000'],
            'reason' => ['required_without:correction_reason', 'string', 'max:2000'],
            'opening_odometer_km' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999999.9', 'decimal:0,1'],
            'closing_odometer_km' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999999.9', 'decimal:0,1'],
            'actual_departure_at' => ['sometimes', 'nullable', 'date'],
            'actual_arrival_at' => ['sometimes', 'nullable', 'date'],
            'operational_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'incident_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'not_operated_reason' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'attendance' => ['sometimes', 'array'],
            'attendance.*.reservation_id' => [
                'required',
                'integer',
                'distinct',
                'exists:shuttle_reservations,id',
            ],
            'attendance.*.status' => [
                'required',
                Rule::in([
                    ServiceAttendanceStatus::Boarded->value,
                    ServiceAttendanceStatus::NoShow->value,
                ]),
            ],
            'attendance.*.boarded_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $correctableFields = [
                    'opening_odometer_km',
                    'closing_odometer_km',
                    'actual_departure_at',
                    'actual_arrival_at',
                    'operational_notes',
                    'incident_notes',
                    'not_operated_reason',
                    'attendance',
                ];

                if (! collect($correctableFields)->contains(
                    fn (string $field): bool => $this->exists($field)
                )) {
                    $validator->errors()->add(
                        'record',
                        'Provide at least one field to correct.'
                    );
                }
            },
        ];
    }
}
