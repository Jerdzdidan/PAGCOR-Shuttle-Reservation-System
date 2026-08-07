<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Models\ShuttleReservation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangeReservationSeatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $employee = $this->user('employee');
        $reservation = $this->route('reservation');

        return $employee instanceof Employee
            && $reservation instanceof ShuttleReservation
            && $reservation->employee_id === $employee->getKey();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'seat_number' => ['required', 'integer', 'min:1'],
        ];
    }
}
