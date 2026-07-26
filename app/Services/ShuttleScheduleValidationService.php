<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ShuttleScheduleValidationService
{
    public function __construct(private ShuttleSeatPolicy $seatPolicy) {}

    public function validate(
        FormRequest $request,
        Validator $validator,
        ?ShuttleSchedule $currentSchedule = null,
    ): void {
        $vehicleId = $this->integerId($request->input('vehicle_id'));
        $driverId = $this->integerId($request->input('driver_id'));
        $routeId = $this->integerId($request->input('route_id'));

        if ($vehicleId === null || $driverId === null || $routeId === null) {
            return;
        }

        $vehicle = Vehicle::query()->find($vehicleId);
        $driver = Driver::query()->find($driverId);
        $route = ShuttleRoute::query()->find($routeId);

        if ($vehicle === null || $driver === null || $route === null) {
            return;
        }

        $this->validateActiveDependencies($request, $validator, $vehicle, $driver, $route);
        $this->validateCapacity($request, $validator, $vehicle);

        if ($validator->errors()->hasAny([
            'vehicle_id',
            'capacity_override',
            'priority_seats',
            'priority_seats.*',
            'unavailable_seats',
            'unavailable_seats.*',
        ])) {
            return;
        }

        $candidateSchedule = $this->candidateSchedule(
            $request,
            $vehicle,
            $currentSchedule
        );
        $this->validateSeatConfiguration($validator, $candidateSchedule);

        if ($currentSchedule !== null) {
            $this->validateExistingReservations(
                $validator,
                $currentSchedule,
                $candidateSchedule
            );
        }

        $this->validateDuplicateSchedule($request, $validator, $currentSchedule);
    }

    private function validateActiveDependencies(
        FormRequest $request,
        Validator $validator,
        Vehicle $vehicle,
        Driver $driver,
        ShuttleRoute $route,
    ): void {
        if ($request->input('status') !== 'ACTIVE') {
            return;
        }

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

    private function validateCapacity(
        FormRequest $request,
        Validator $validator,
        Vehicle $vehicle,
    ): void {
        if (
            $request->filled('capacity_override')
            && (int) $request->input('capacity_override') > $vehicle->capacity
        ) {
            $validator->errors()->add(
                'capacity_override',
                'Capacity override cannot exceed the vehicle capacity.'
            );
        }
    }

    private function validateSeatConfiguration(
        Validator $validator,
        ShuttleSchedule $candidateSchedule,
    ): void {
        $capacity = $this->seatPolicy->effectiveCapacity($candidateSchedule);
        $prioritySeats = $candidateSchedule->priority_seats;
        $unavailableSeats = $candidateSchedule->unavailable_seats;

        if ($prioritySeats !== null && ! is_array($prioritySeats)) {
            return;
        }

        if ($unavailableSeats !== null && ! is_array($unavailableSeats)) {
            return;
        }

        if ($this->containsSeatOutsideCapacity($prioritySeats ?? [], $capacity)) {
            $validator->errors()->add(
                'priority_seats',
                'Priority seats must be within the effective shuttle capacity.'
            );
        }

        if ($this->containsSeatOutsideCapacity($unavailableSeats ?? [], $capacity)) {
            $validator->errors()->add(
                'unavailable_seats',
                'Unavailable seats must be within the effective shuttle capacity.'
            );
        }

        if (array_intersect(
            $this->seatPolicy->effectivePrioritySeats($candidateSchedule),
            $this->seatPolicy->effectiveUnavailableSeats($candidateSchedule)
        ) !== []) {
            $validator->errors()->add(
                'unavailable_seats',
                'A seat cannot be both priority-only and unavailable.'
            );
        }
    }

    private function validateExistingReservations(
        Validator $validator,
        ShuttleSchedule $currentSchedule,
        ShuttleSchedule $candidateSchedule,
    ): void {
        $operatingTimezone = (string) config('shuttle.operating_timezone', 'Asia/Manila');
        $now = CarbonImmutable::now($operatingTimezone);
        $futureReservations = $currentSchedule->reservations()
            ->with('employee:employee_id,priority_status')
            ->whereDate(
                'travel_date',
                '>=',
                $now->startOfDay()
            )
            ->get([
                'id',
                'employee_id',
                'shuttle_schedule_id',
                'travel_date',
                'seat_number',
            ])
            ->filter(
                fn ($reservation): bool => CarbonImmutable::parse(
                    $reservation->travel_date->toDateString()
                        .' '.(string) $currentSchedule->departure_time,
                    $operatingTimezone
                )->gt($now)
            );

        if ($futureReservations->isEmpty()) {
            return;
        }

        $capacity = $this->seatPolicy->effectiveCapacity($candidateSchedule);
        $prioritySeats = $this->seatPolicy->effectivePrioritySeats($candidateSchedule);
        $unavailableSeats = $this->seatPolicy->effectiveUnavailableSeats($candidateSchedule);

        if ($futureReservations->contains(
            fn ($reservation): bool => $reservation->seat_number > $capacity
        )) {
            $validator->errors()->add(
                'capacity_override',
                'The effective capacity cannot exclude a seat with a future reservation.'
            );
        }

        if ($futureReservations->contains(
            fn ($reservation): bool => in_array($reservation->seat_number, $unavailableSeats, true)
        )) {
            $validator->errors()->add(
                'unavailable_seats',
                'An unavailable seat cannot contain a future reservation.'
            );
        }

        if ($futureReservations->contains(
            fn ($reservation): bool => ! $reservation->employee->isPriority()
                && in_array($reservation->seat_number, $prioritySeats, true)
        )) {
            $validator->errors()->add(
                'priority_seats',
                'A regular employee already has a future reservation in one of these priority seats.'
            );
        }
    }

    private function validateDuplicateSchedule(
        FormRequest $request,
        Validator $validator,
        ?ShuttleSchedule $currentSchedule,
    ): void {
        if ($request->input('status') !== 'ACTIVE' || $validator->errors()->isNotEmpty()) {
            return;
        }

        $candidateDays = (array) $request->input('operating_days', []);
        $query = ShuttleSchedule::query()
            ->where('route_id', $request->input('route_id'))
            ->where('vehicle_id', $request->input('vehicle_id'))
            ->where('direction', $request->input('direction'))
            ->where('status', 'ACTIVE');

        if ($currentSchedule !== null) {
            $query->whereKeyNot($currentSchedule->getKey());
        }

        foreach ($query->get() as $schedule) {
            if (
                mb_substr((string) $schedule->departure_time, 0, 5)
                    === mb_substr((string) $request->input('departure_time'), 0, 5)
                && array_intersect($candidateDays, $schedule->operating_days ?? []) !== []
                && $this->dateRangesOverlap($request, $schedule)
            ) {
                $validator->errors()->add(
                    'departure_time',
                    'An active schedule with the same route, vehicle, direction, time, and operating day already exists.'
                );

                return;
            }
        }
    }

    private function dateRangesOverlap(
        FormRequest $request,
        ShuttleSchedule $schedule,
    ): bool {
        $candidateFrom = (string) $request->input('effective_from');
        $candidateUntil = (string) ($request->input('effective_until') ?: '9999-12-31');
        $existingFrom = $schedule->effective_from?->toDateString() ?? '0000-01-01';
        $existingUntil = $schedule->effective_until?->toDateString() ?? '9999-12-31';

        return $candidateFrom <= $existingUntil && $existingFrom <= $candidateUntil;
    }

    private function candidateSchedule(
        FormRequest $request,
        Vehicle $vehicle,
        ?ShuttleSchedule $currentSchedule,
    ): ShuttleSchedule {
        $candidateSchedule = new ShuttleSchedule([
            'vehicle_id' => $vehicle->getKey(),
            'capacity_override' => $request->exists('capacity_override')
                ? ($request->filled('capacity_override')
                    ? (int) $request->input('capacity_override')
                    : null)
                : $currentSchedule?->capacity_override,
            'priority_seats' => $request->exists('priority_seats')
                ? $request->input('priority_seats')
                : $currentSchedule?->priority_seats,
            'unavailable_seats' => $request->exists('unavailable_seats')
                ? $request->input('unavailable_seats')
                : $currentSchedule?->unavailable_seats,
        ]);
        $candidateSchedule->setRelation('vehicle', $vehicle);

        return $candidateSchedule;
    }

    /** @param array<mixed> $seats */
    private function containsSeatOutsideCapacity(array $seats, int $capacity): bool
    {
        return collect($seats)->contains(
            fn (mixed $seat): bool => is_numeric($seat)
                && ((int) $seat < 1 || (int) $seat > $capacity)
        );
    }

    private function integerId(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $validatedId = filter_var($value, FILTER_VALIDATE_INT);

        return $validatedId === false ? null : (int) $validatedId;
    }
}
