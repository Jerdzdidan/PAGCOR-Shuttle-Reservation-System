<?php

namespace App\Http\Requests\Employee\Auth;

use App\Models\Employee;
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
            'version' => ['required', 'integer', 'min:1'],
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

                $employee = $this->route('employee');

                if (
                    ! $employee instanceof Employee
                    || ! $this->hasValidSignature(false)
                    || $this->integer('version') !== $employee->qr_token_version
                ) {
                    $validator->errors()->add(
                        'credential',
                        'This employee QR code is invalid or has been replaced.',
                    );
                }
            },
        ];
    }
}
