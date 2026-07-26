<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Models\ShuttleRoute;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseSchedulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('employee') instanceof Employee;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = CarbonImmutable::now($this->operatingTimezone())->startOfDay();
        $lastBookingDate = $today->addDays($this->bookingHorizonDays());

        return [
            'date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.$lastBookingDate->toDateString(),
            ],
            'route_id' => ['nullable', 'integer', Rule::exists(ShuttleRoute::class, 'id')],
            'direction' => ['nullable', 'string', Rule::in(['OUTBOUND', 'RETURN'])],
        ];
    }

    private function operatingTimezone(): string
    {
        return (string) config('shuttle.operating_timezone', 'Asia/Manila');
    }

    private function bookingHorizonDays(): int
    {
        return max(0, (int) config('shuttle.booking_horizon_days', 30));
    }
}
