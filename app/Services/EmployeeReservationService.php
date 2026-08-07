<?php

namespace App\Services;

use App\Enums\ServiceAttendanceStatus;
use App\Enums\ServiceOccurrenceStatus;
use App\Enums\ShuttleActivityEventType;
use App\Mail\WaitlistPromotionMail;
use App\Models\Employee;
use App\Models\ShuttleActivityEvent;
use App\Models\ShuttleReservation;
use App\Models\ShuttleSchedule;
use App\Models\ShuttleServiceAttendance;
use App\Models\ShuttleServiceOccurrence;
use App\Models\ShuttleWaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmployeeReservationService
{
    public function __construct(private ShuttleSeatPolicy $seatPolicy) {}

    public function reserve(
        Employee $employee,
        int $scheduleId,
        string $travelDate,
        int $seatNumber,
    ): ShuttleReservation {
        return DB::transaction(function () use ($employee, $scheduleId, $travelDate, $seatNumber): ShuttleReservation {
            $schedule = $this->lockSchedule($scheduleId);
            [$date, $occurrence] = $this->assertReservableOccurrence($schedule, $travelDate);
            $employee = $this->lockActiveEmployee($employee);
            $seatConfiguration = $occurrence ?? $schedule;

            $this->assertEmployeeHasNoDailyEntry($employee, $schedule, $date);

            $capacity = $this->seatPolicy->effectiveCapacity($seatConfiguration);

            if ($seatNumber < 1 || $seatNumber > $capacity) {
                throw ValidationException::withMessages([
                    'seat_number' => 'The selected seat is outside this shuttle’s capacity.',
                ]);
            }

            if ($this->seatPolicy->isUnavailableSeat($seatConfiguration, $seatNumber)) {
                throw ValidationException::withMessages([
                    'seat_number' => 'The selected seat is unavailable for this schedule.',
                ]);
            }

            if (
                ! $employee->isPriority()
                && $this->seatPolicy->isPrioritySeat($seatConfiguration, $seatNumber)
            ) {
                throw ValidationException::withMessages([
                    'seat_number' => 'The selected seat is reserved for priority employees.',
                ]);
            }

            $seatIsOccupied = ShuttleReservation::query()
                ->where('shuttle_schedule_id', $schedule->id)
                ->whereDate('travel_date', $date)
                ->where('seat_number', $seatNumber)
                ->lockForUpdate()
                ->first(['id']);

            if ($seatIsOccupied !== null) {
                throw ValidationException::withMessages([
                    'seat_number' => 'That seat was just reserved. Please choose another available seat.',
                ]);
            }

            $reservation = ShuttleReservation::query()->create([
                'employee_id' => $employee->getKey(),
                'shuttle_schedule_id' => $schedule->id,
                'travel_date' => $date->toDateString(),
                'seat_number' => $seatNumber,
                'source' => ShuttleReservation::SOURCE_SELECTED,
                'reserved_at' => now(),
            ]);

            ShuttleActivityEvent::record(
                employee: $employee,
                schedule: $schedule,
                travelDate: $date,
                eventType: ShuttleActivityEventType::ReservationCreated,
                seatNumber: $seatNumber,
                metadata: [
                    'reservation_id' => $reservation->id,
                    'source' => $reservation->source,
                ],
                occurrence: $occurrence,
            );

            $this->refreshOccurrenceCounts($occurrence);

            return $reservation;
        }, 5);
    }

    /**
     * Move an existing reservation to another seat on the same shuttle occurrence,
     * without releasing the current seat to the waitlist.
     */
    public function changeSeat(
        Employee $employee,
        ShuttleReservation $reservation,
        int $seatNumber,
    ): ShuttleReservation {
        return DB::transaction(function () use ($employee, $reservation, $seatNumber): ShuttleReservation {
            $date = $this->parseTravelDate($reservation->travel_date->toDateString());
            $schedule = $this->lockSchedule((int) $reservation->shuttle_schedule_id);
            $occurrence = $this->occurrenceFor($schedule, $date, lock: true);
            $employee = $this->lockActiveEmployee($employee);
            $lockedReservation = ShuttleReservation::query()
                ->whereKey($reservation->getKey())
                ->where('employee_id', $employee->getKey())
                ->whereDate('travel_date', $date)
                ->lockForUpdate()
                ->first();

            if ($lockedReservation === null) {
                throw ValidationException::withMessages([
                    'reservation' => 'This reservation is no longer available.',
                ]);
            }

            $this->assertBeforeDeparture($schedule, $date, $occurrence);

            $previousSeat = (int) $lockedReservation->seat_number;

            if ($previousSeat === $seatNumber) {
                throw ValidationException::withMessages([
                    'seat_number' => 'You are already seated in this seat. Choose a different one.',
                ]);
            }

            $seatConfiguration = $occurrence ?? $schedule;
            $capacity = $this->seatPolicy->effectiveCapacity($seatConfiguration);

            if ($seatNumber < 1 || $seatNumber > $capacity) {
                throw ValidationException::withMessages([
                    'seat_number' => 'The selected seat is outside this shuttle’s capacity.',
                ]);
            }

            if ($this->seatPolicy->isUnavailableSeat($seatConfiguration, $seatNumber)) {
                throw ValidationException::withMessages([
                    'seat_number' => 'The selected seat is unavailable for this schedule.',
                ]);
            }

            if (
                ! $employee->isPriority()
                && $this->seatPolicy->isPrioritySeat($seatConfiguration, $seatNumber)
            ) {
                throw ValidationException::withMessages([
                    'seat_number' => 'The selected seat is reserved for priority employees.',
                ]);
            }

            $seatIsOccupied = ShuttleReservation::query()
                ->where('shuttle_schedule_id', $schedule->id)
                ->whereDate('travel_date', $date)
                ->where('seat_number', $seatNumber)
                ->whereKeyNot($lockedReservation->getKey())
                ->lockForUpdate()
                ->first(['id']);

            if ($seatIsOccupied !== null) {
                throw ValidationException::withMessages([
                    'seat_number' => 'That seat was just reserved. Please choose another available seat.',
                ]);
            }

            $lockedReservation->forceFill(['seat_number' => $seatNumber])->save();
            $this->syncAttendanceSeat($occurrence, $lockedReservation, $seatNumber);

            ShuttleActivityEvent::record(
                employee: $employee,
                schedule: $schedule,
                travelDate: $date,
                eventType: ShuttleActivityEventType::ReservationSeatChanged,
                seatNumber: $seatNumber,
                metadata: [
                    'reservation_id' => $lockedReservation->id,
                    'source' => $lockedReservation->source,
                    'previous_seat_number' => $previousSeat,
                ],
                occurrence: $occurrence,
            );

            return $lockedReservation->refresh();
        }, 5);
    }

    public function joinWaitlist(
        Employee $employee,
        int $scheduleId,
        string $travelDate,
    ): ShuttleWaitlistEntry {
        return DB::transaction(function () use ($employee, $scheduleId, $travelDate): ShuttleWaitlistEntry {
            $schedule = $this->lockSchedule($scheduleId);
            [$date, $occurrence] = $this->assertReservableOccurrence($schedule, $travelDate);
            $employee = $this->lockActiveEmployee($employee);
            $seatConfiguration = $occurrence ?? $schedule;

            $this->assertEmployeeHasNoDailyEntry($employee, $schedule, $date);

            if (! $this->waitlistEnabled($schedule, $occurrence)) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'The waitlist is disabled for this shuttle schedule.',
                ]);
            }

            $eligibleSeats = $this->seatPolicy->eligibleSeats(
                $seatConfiguration,
                $employee->isPriority()
            );

            if ($eligibleSeats === []) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'This shuttle has no seats that can be assigned to you.',
                ]);
            }

            $occupiedEligibleSeats = ShuttleReservation::query()
                ->where('shuttle_schedule_id', $schedule->id)
                ->whereDate('travel_date', $date)
                ->whereIn('seat_number', $eligibleSeats)
                ->lockForUpdate()
                ->pluck('seat_number')
                ->unique();

            if ($occupiedEligibleSeats->count() < count($eligibleSeats)) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'An eligible seat is still available. Select a seat before joining the waitlist.',
                ]);
            }

            $waitlistCapacity = $this->waitlistCapacity($schedule, $occurrence);

            if ($waitlistCapacity !== null) {
                $waitlistCount = ShuttleWaitlistEntry::query()
                    ->where('shuttle_schedule_id', $schedule->id)
                    ->whereDate('travel_date', $date)
                    ->count();

                if ($waitlistCount >= $waitlistCapacity) {
                    throw ValidationException::withMessages([
                        'schedule_id' => 'The waitlist for this shuttle is already full.',
                    ]);
                }
            }

            $waitlistEntry = ShuttleWaitlistEntry::query()->create([
                'employee_id' => $employee->getKey(),
                'shuttle_schedule_id' => $schedule->id,
                'travel_date' => $date->toDateString(),
                'queued_at' => now(),
            ]);

            ShuttleActivityEvent::record(
                employee: $employee,
                schedule: $schedule,
                travelDate: $date,
                eventType: ShuttleActivityEventType::WaitlistJoined,
                metadata: ['waitlist_entry_id' => $waitlistEntry->id],
                occurrence: $occurrence,
            );

            return $waitlistEntry;
        }, 5);
    }

    public function cancel(
        Employee $employee,
        ShuttleReservation $reservation,
    ): ?ShuttleReservation {
        return DB::transaction(function () use ($employee, $reservation): ?ShuttleReservation {
            $date = $this->parseTravelDate($reservation->travel_date->toDateString());
            $schedule = $this->lockSchedule((int) $reservation->shuttle_schedule_id);
            $occurrence = $this->occurrenceFor($schedule, $date, lock: true);
            $lockedReservation = ShuttleReservation::query()
                ->whereKey($reservation->getKey())
                ->where('employee_id', $employee->getKey())
                ->whereDate('travel_date', $date)
                ->lockForUpdate()
                ->first();

            if ($lockedReservation === null) {
                throw ValidationException::withMessages([
                    'reservation' => 'This reservation is no longer available.',
                ]);
            }

            $this->assertBeforeDeparture($schedule, $date, $occurrence);

            $releasedSeat = $lockedReservation->seat_number;
            $cancelledAt = CarbonImmutable::now($this->operatingTimezone());
            $scheduledDepartureAt = $this->departureAt($schedule, $date, $occurrence);
            $cancellationLeadMinutes = max(
                0,
                intdiv(
                    $scheduledDepartureAt->getTimestamp() - $cancelledAt->getTimestamp(),
                    60
                )
            );

            ShuttleActivityEvent::record(
                employee: $employee,
                schedule: $schedule,
                travelDate: $date,
                eventType: ShuttleActivityEventType::ReservationCancelled,
                seatNumber: $releasedSeat,
                metadata: [
                    'reservation_id' => $lockedReservation->id,
                    'source' => $lockedReservation->source,
                    'reserved_at' => $lockedReservation->reserved_at?->toIso8601String(),
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                    'scheduled_departure_at' => $scheduledDepartureAt->toIso8601String(),
                    'cancellation_lead_minutes' => $cancellationLeadMinutes,
                ],
                occurrence: $occurrence,
            );

            $this->clearOpenAttendance($occurrence, $lockedReservation);
            $lockedReservation->delete();

            $seatConfiguration = $occurrence ?? $schedule;
            $promotedReservation = null;

            if (in_array($releasedSeat, $this->seatPolicy->availableSeats($seatConfiguration), true)) {
                $waitlistEntry = $this->nextWaitlistEntry(
                    $schedule,
                    $date,
                    $this->seatPolicy->isPrioritySeat($seatConfiguration, $releasedSeat),
                );

                if ($waitlistEntry !== null) {
                    $promotedReservation = ShuttleReservation::query()->create([
                        'employee_id' => $waitlistEntry->employee_id,
                        'shuttle_schedule_id' => $schedule->id,
                        'travel_date' => $date->toDateString(),
                        'seat_number' => $releasedSeat,
                        'source' => ShuttleReservation::SOURCE_AUTO_ASSIGNED,
                        'reserved_at' => now(),
                    ]);

                    $waitlistEntry->delete();

                    $promotedEmployee = $waitlistEntry->employee;

                    ShuttleActivityEvent::record(
                        employee: $promotedEmployee,
                        schedule: $schedule,
                        travelDate: $date,
                        eventType: ShuttleActivityEventType::WaitlistPromoted,
                        seatNumber: $releasedSeat,
                        metadata: [
                            'waitlist_entry_id' => $waitlistEntry->id,
                            'reservation_id' => $promotedReservation->id,
                            'queued_at' => $waitlistEntry->queued_at?->toIso8601String(),
                        ],
                        occurrence: $occurrence,
                    );

                    $promotionDetails = $this->promotionDetails(
                        $schedule,
                        $date,
                        $occurrence,
                    );
                    $promotedEmployeeEmail = $promotedEmployee->email;
                    $promotedEmployeeName = $promotedEmployee->name;

                    DB::afterCommit(function () use (
                        $promotedEmployeeEmail,
                        $promotedEmployeeName,
                        $promotionDetails,
                        $releasedSeat,
                    ): void {
                        Mail::to($promotedEmployeeEmail)->queue(new WaitlistPromotionMail(
                            employeeName: $promotedEmployeeName,
                            routeName: $promotionDetails['route_name'],
                            travelDate: $promotionDetails['travel_date'],
                            departureTime: $promotionDetails['departure_time'],
                            plateNumber: $promotionDetails['plate_number'],
                            seatNumber: $releasedSeat,
                        ));
                    });
                }
            }

            $this->refreshOccurrenceCounts($occurrence);

            return $promotedReservation;
        }, 5);
    }

    public function withdraw(
        Employee $employee,
        ShuttleWaitlistEntry $waitlistEntry,
    ): void {
        DB::transaction(function () use ($employee, $waitlistEntry): void {
            $date = $this->parseTravelDate($waitlistEntry->travel_date->toDateString());
            $schedule = $this->lockSchedule((int) $waitlistEntry->shuttle_schedule_id);
            $occurrence = $this->occurrenceFor($schedule, $date, lock: true);
            $lockedEntry = ShuttleWaitlistEntry::query()
                ->whereKey($waitlistEntry->getKey())
                ->where('employee_id', $employee->getKey())
                ->whereDate('travel_date', $date)
                ->lockForUpdate()
                ->first();

            if ($lockedEntry === null) {
                throw ValidationException::withMessages([
                    'waitlist' => 'This waitlist entry is no longer available.',
                ]);
            }

            $this->assertBeforeDeparture($schedule, $date, $occurrence);

            ShuttleActivityEvent::record(
                employee: $employee,
                schedule: $schedule,
                travelDate: $date,
                eventType: ShuttleActivityEventType::WaitlistWithdrawn,
                metadata: [
                    'waitlist_entry_id' => $lockedEntry->id,
                    'queued_at' => $lockedEntry->queued_at?->toIso8601String(),
                ],
                occurrence: $occurrence,
            );

            $lockedEntry->delete();
        }, 5);
    }

    private function lockSchedule(int $scheduleId): ShuttleSchedule
    {
        $schedule = ShuttleSchedule::query()
            ->with([
                'route:id,name,origin,destination,status',
                'vehicle:id,plate_number,vehicle_type,capacity,status',
                'driver:id,name,status',
            ])
            ->lockForUpdate()
            ->find($scheduleId);

        if ($schedule === null) {
            throw ValidationException::withMessages([
                'schedule_id' => 'The selected shuttle schedule is unavailable.',
            ]);
        }

        return $schedule;
    }

    /**
     * @return array{CarbonImmutable, ShuttleServiceOccurrence|null}
     */
    private function assertReservableOccurrence(
        ShuttleSchedule $schedule,
        string $travelDate,
    ): array {
        $date = $this->parseTravelDate($travelDate);
        $today = CarbonImmutable::now($this->operatingTimezone())->startOfDay();

        /*
         * Seats are only handed out on the day of travel. Later dates stay
         * browsable so employees can see what is scheduled, but nothing can be
         * held in advance.
         */
        if (! $date->equalTo($today)) {
            throw ValidationException::withMessages([
                'travel_date' => $date->lt($today)
                    ? 'That travel date has already passed.'
                    : 'Seats can only be reserved on the day of travel.',
            ]);
        }

        $occurrence = $this->occurrenceFor($schedule, $date, lock: true);

        if ($occurrence === null) {
            throw ValidationException::withMessages([
                'schedule_id' => 'This departure is not available for reservations today.',
            ]);
        }

        $this->assertBeforeDeparture($schedule, $date, $occurrence);

        return [$date, $occurrence];
    }

    private function assertBeforeDeparture(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        ?ShuttleServiceOccurrence $occurrence = null,
    ): void {
        if (
            $occurrence !== null
            && $occurrence->status !== ServiceOccurrenceStatus::Scheduled
        ) {
            throw ValidationException::withMessages([
                'travel_date' => 'Reservations and waitlist changes close at departure time.',
            ]);
        }

        if (
            $this->departureAt($schedule, $date, $occurrence)->lessThanOrEqualTo(
                CarbonImmutable::now($this->operatingTimezone())
            )
        ) {
            throw ValidationException::withMessages([
                'travel_date' => 'Reservations and waitlist changes close at departure time.',
            ]);
        }
    }

    /**
     * An employee may hold only one reservation or waitlist entry per travel date,
     * across every schedule.
     */
    private function assertEmployeeHasNoDailyEntry(
        Employee $employee,
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
    ): void {
        $reservation = ShuttleReservation::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('travel_date', $date)
            ->lockForUpdate()
            ->first(['id', 'shuttle_schedule_id']);

        if ($reservation !== null) {
            throw ValidationException::withMessages([
                'schedule_id' => (int) $reservation->shuttle_schedule_id === (int) $schedule->id
                    ? 'You already have a reservation for this shuttle occurrence.'
                    : 'You already have a reservation for another shuttle on this date. Only one schedule per day can be booked.',
            ]);
        }

        $waitlistEntry = ShuttleWaitlistEntry::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('travel_date', $date)
            ->lockForUpdate()
            ->first(['id', 'shuttle_schedule_id']);

        if ($waitlistEntry !== null) {
            throw ValidationException::withMessages([
                'schedule_id' => (int) $waitlistEntry->shuttle_schedule_id === (int) $schedule->id
                    ? 'You are already on the waitlist for this shuttle occurrence.'
                    : 'You are already on the waitlist for another shuttle on this date. Only one schedule per day can be booked.',
            ]);
        }
    }

    private function nextWaitlistEntry(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        bool $priorityOnly,
    ): ?ShuttleWaitlistEntry {
        $priorityEntry = $this->waitlistOccurrenceQuery($schedule, $date)
            ->whereIn(
                'employee_id',
                Employee::query()
                    ->select('employee_id')
                    ->where('status', Employee::STATUS_ACTIVE)
                    ->where('priority_status', Employee::PRIORITY_STATUS_PRIORITY)
            )
            ->first();

        if ($priorityEntry !== null || $priorityOnly) {
            return $priorityEntry;
        }

        return $this->waitlistOccurrenceQuery($schedule, $date)->first();
    }

    private function waitlistOccurrenceQuery(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
    ): Builder {
        return ShuttleWaitlistEntry::query()
            ->with('employee:employee_id,name,email,priority_status,status')
            ->where('shuttle_schedule_id', $schedule->id)
            ->whereDate('travel_date', $date)
            ->whereIn(
                'employee_id',
                Employee::query()
                    ->select('employee_id')
                    ->where('status', Employee::STATUS_ACTIVE),
            )
            ->orderBy('queued_at')
            ->orderBy('id')
            ->lockForUpdate();
    }

    private function lockActiveEmployee(Employee $employee): Employee
    {
        $lockedEmployee = Employee::query()
            ->whereKey($employee->getKey())
            ->lockForUpdate()
            ->first();

        if ($lockedEmployee === null || ! $lockedEmployee->isActive()) {
            throw ValidationException::withMessages([
                'employee' => 'Your employee access is inactive. Contact Shuttle Operations for assistance.',
            ]);
        }

        return $lockedEmployee;
    }

    private function occurrenceFor(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        bool $lock = false,
    ): ?ShuttleServiceOccurrence {
        return ShuttleServiceOccurrence::query()
            ->where('shuttle_schedule_id', $schedule->getKey())
            ->whereDate('travel_date', $date)
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->first();
    }

    private function departureAt(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        ?ShuttleServiceOccurrence $occurrence = null,
    ): CarbonImmutable {
        if ($occurrence !== null) {
            return $occurrence->scheduled_departure_at->toImmutable();
        }

        return CarbonImmutable::parse(
            $date->toDateString().' '.(string) $schedule->departure_time,
            $this->operatingTimezone()
        );
    }

    private function clearOpenAttendance(
        ?ShuttleServiceOccurrence $occurrence,
        ShuttleReservation $reservation,
    ): void {
        if ($occurrence === null || $occurrence->isFinalized()) {
            return;
        }

        ShuttleServiceAttendance::query()
            ->where('shuttle_service_occurrence_id', $occurrence->getKey())
            ->where('shuttle_reservation_id', $reservation->getKey())
            ->lockForUpdate()
            ->get()
            ->each
            ->delete();
    }

    /**
     * Keep any attendance snapshot aligned with the seat the employee moved to.
     */
    private function syncAttendanceSeat(
        ?ShuttleServiceOccurrence $occurrence,
        ShuttleReservation $reservation,
        int $seatNumber,
    ): void {
        if ($occurrence === null || $occurrence->isFinalized()) {
            return;
        }

        ShuttleServiceAttendance::query()
            ->where('shuttle_service_occurrence_id', $occurrence->getKey())
            ->where('shuttle_reservation_id', $reservation->getKey())
            ->lockForUpdate()
            ->get()
            ->each
            ->update(['seat_number' => $seatNumber]);
    }

    private function refreshOccurrenceCounts(
        ?ShuttleServiceOccurrence $occurrence,
    ): void {
        if ($occurrence === null || $occurrence->isFinalized()) {
            return;
        }

        $occurrence->forceFill([
            'reservation_count' => ShuttleReservation::query()
                ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
                ->whereDate('travel_date', $occurrence->travel_date)
                ->count(),
            'boarded_count' => ShuttleServiceAttendance::query()
                ->where('shuttle_service_occurrence_id', $occurrence->getKey())
                ->where('status', ServiceAttendanceStatus::Boarded)
                ->count(),
            'no_show_count' => ShuttleServiceAttendance::query()
                ->where('shuttle_service_occurrence_id', $occurrence->getKey())
                ->where('status', ServiceAttendanceStatus::NoShow)
                ->count(),
        ])->save();
    }

    private function waitlistEnabled(
        ShuttleSchedule $schedule,
        ?ShuttleServiceOccurrence $occurrence,
    ): bool {
        return (bool) ($occurrence?->waitlist_enabled ?? $schedule->waitlist_enabled);
    }

    private function waitlistCapacity(
        ShuttleSchedule $schedule,
        ?ShuttleServiceOccurrence $occurrence,
    ): ?int {
        $capacity = $occurrence !== null
            ? $occurrence->waitlist_capacity
            : $schedule->waitlist_capacity;

        return $capacity === null ? null : (int) $capacity;
    }

    /**
     * @return array{
     *     route_name: string,
     *     travel_date: string,
     *     departure_time: string,
     *     plate_number: string
     * }
     */
    private function promotionDetails(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        ?ShuttleServiceOccurrence $occurrence,
    ): array {
        return [
            'route_name' => $occurrence?->route_name ?? $schedule->route->name,
            'travel_date' => $date->format('F j, Y'),
            'departure_time' => $this
                ->departureAt($schedule, $date, $occurrence)
                ->format('g:i A'),
            'plate_number' => $occurrence?->plate_number ?? $schedule->vehicle->plate_number,
        ];
    }

    private function parseTravelDate(string $travelDate): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::parse($travelDate, $this->operatingTimezone())->startOfDay();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'travel_date' => 'Enter a valid travel date.',
            ]);
        }

        if ($date->toDateString() !== $travelDate) {
            throw ValidationException::withMessages([
                'travel_date' => 'Enter a valid travel date in YYYY-MM-DD format.',
            ]);
        }

        return $date;
    }

    private function operatingTimezone(): string
    {
        return (string) config('shuttle.operating_timezone', 'Asia/Manila');
    }
}
