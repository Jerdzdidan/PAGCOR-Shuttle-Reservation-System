<?php

namespace App\Http\Requests\Employee\Auth;

use App\Services\EmployeeIdentifier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EmployeeQrLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version' => ['prohibited'],
            'signature' => ['required', 'string', 'size:64'],
        ];
    }

    /**
     * Get the validation callbacks for the request.
     *
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $employeeCode = $this->route('employeeCode');

                if (
                    ! is_string($employeeCode)
                    || preg_match(EmployeeIdentifier::PATTERN, $employeeCode) !== 1
                    || ! $this->hasValidSignature(false)
                ) {
                    $validator->errors()->add(
                        'credential',
                        'This employee QR code is invalid.',
                    );
                }
            },
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.prohibited' => 'This employee QR code uses an obsolete format. Download the permanent QR code from Employee Management.',
        ];
    }
}
