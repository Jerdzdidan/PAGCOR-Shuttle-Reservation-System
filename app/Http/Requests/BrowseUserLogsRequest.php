<?php

namespace App\Http\Requests;

use App\Enums\UserLogAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BrowseUserLogsRequest extends FormRequest
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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'action' => ['nullable', Rule::enum(UserLogAction::class)],
            'subject_type' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
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
