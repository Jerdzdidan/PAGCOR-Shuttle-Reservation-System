<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Models\ShuttleWaitlistEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawWaitlistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $employee = $this->user('employee');
        $waitlistEntry = $this->route('waitlistEntry');

        return $employee instanceof Employee
            && $waitlistEntry instanceof ShuttleWaitlistEntry
            && $waitlistEntry->employee_id === $employee->getKey();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
