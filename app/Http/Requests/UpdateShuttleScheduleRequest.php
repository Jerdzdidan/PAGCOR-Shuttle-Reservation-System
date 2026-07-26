<?php

namespace App\Http\Requests;

use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateShuttleScheduleRequest extends FormRequest
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
            'status' => ['required', 'in:ACTIVE,INACTIVE'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $vehicle = Vehicle::find($this->input('vehicle_id'));
            $driver = Driver::find($this->input('driver_id'));
            $route = ShuttleRoute::find($this->input('route_id'));

            if (! $vehicle || ! $driver || ! $route) {
                return;
            }

            if ($this->input('status') === 'ACTIVE') {
                if ($vehicle->status !== 'ACTIVE') {
                    $validator->errors()->add('vehicle_id', 'Active schedules require an active vehicle.');
                }

                if ($driver->status !== 'ACTIVE') {
                    $validator->errors()->add('driver_id', 'Active schedules require an active driver.');
                }

                if ($route->status !== 'ACTIVE') {
                    $validator->errors()->add('route_id', 'Active schedules require an active route.');
                }
            }

            if ($this->filled('capacity_override') && (int) $this->input('capacity_override') > $vehicle->capacity) {
                $validator->errors()->add('capacity_override', 'Capacity override cannot exceed the vehicle capacity.');
            }

            $this->validateDuplicateSchedule($validator);
        });
    }

    protected function validateDuplicateSchedule(Validator $validator): void
    {
        if ($this->input('status') !== 'ACTIVE' || $validator->errors()->isNotEmpty()) {
            return;
        }

        $candidateDays = (array) $this->input('operating_days', []);
        $current = $this->route('schedule');
        $query = ShuttleSchedule::query()
            ->where('route_id', $this->input('route_id'))
            ->where('vehicle_id', $this->input('vehicle_id'))
            ->where('direction', $this->input('direction'))
            ->where('status', 'ACTIVE');

        if ($current instanceof ShuttleSchedule) {
            $query->whereKeyNot($current->getKey());
        }

        foreach ($query->get() as $schedule) {
            if (substr((string) $schedule->departure_time, 0, 5) === substr((string) $this->input('departure_time'), 0, 5) && array_intersect($candidateDays, $schedule->operating_days ?? []) !== [] && $this->dateRangesOverlap($schedule)) {
                $validator->errors()->add('departure_time', 'An active schedule with the same route, vehicle, direction, time, and operating day already exists.');
                return;
            }
        }
    }

    protected function dateRangesOverlap(ShuttleSchedule $schedule): bool
    {
        $candidateFrom = (string) $this->input('effective_from');
        $candidateUntil = (string) ($this->input('effective_until') ?: '9999-12-31');
        $existingFrom = $schedule->effective_from?->toDateString() ?? '0000-01-01';
        $existingUntil = $schedule->effective_until?->toDateString() ?? '9999-12-31';

        return $candidateFrom <= $existingUntil && $existingFrom <= $candidateUntil;
    }
}
