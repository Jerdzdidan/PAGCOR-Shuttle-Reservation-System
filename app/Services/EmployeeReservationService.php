<?php

namespace App\Services;

use App\Mail\WaitlistPromotionMail;
use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Models\ShuttleSchedule;
use App\Models\ShuttleWaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmployeeReservationService
{
    public function reserve(
        Employee $employee,
        int $scheduleId,
        string $travelDate,
        int $seatNumber,
    ): ShuttleReservation {
        return DB::transaction(function () use ($employee, $scheduleId, $travelDate, $seatNumber): ShuttleReservation {
            $schedule = $this->lockSchedule($scheduleId);
            $date = $this->assertReservableOccurrence($schedule, $travelDate);

            $this->assertEmployeeHasNoEntry($employee, $schedule, $date);

            $capacity = $this->effectiveCapacity($schedule);

            if ($seatNumber < 1 || $seatNumber > $capacity) {
                throw ValidationException::withMessages([
                    'seat_number' => 'The selected seat is outside this shuttle’s capacity.',
                ]);
            }

            if (! $employee->isPriority() && $seatNumber <= $this->prioritySeatCount($capacity)) {
                throw ValidationException::withMessages([
                    'seat_number' => 'Seats 1 through '.$this->prioritySeatCount($capacity).' are reserved for priority employees.',
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

            return ShuttleReservation::query()->create([
                'employee_id' => $employee->getKey(),
                'shuttle_schedule_id' => $schedule->id,
                'travel_date' => $date->toDateString(),
                'seat_number' => $seatNumber,
                'source' => ShuttleReservation::SOURCE_SELECTED,
                'reserved_at' => now(),
            ]);
        }, 5);
    }

    public function joinWaitlist(
        Employee $employee,
        int $scheduleId,
        string $travelDate,
    ): ShuttleWaitlistEntry {
        return DB::transaction(function () use ($employee, $scheduleId, $travelDate): ShuttleWaitlistEntry {
            $schedule = $this->lockSchedule($scheduleId);
            $date = $this->assertReservableOccurrence($schedule, $travelDate);

            $this->assertEmployeeHasNoEntry($employee, $schedule, $date);

            $capacity = $this->effectiveCapacity($schedule);
            $firstEligibleSeat = $employee->isPriority()
                ? 1
                : $this->prioritySeatCount($capacity) + 1;
            $eligibleCapacity = max(0, $capacity - $firstEligibleSeat + 1);

            if ($eligibleCapacity === 0) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'This shuttle has no general seats that can be assigned to regular employees.',
                ]);
            }

            $occupiedEligibleSeats = ShuttleReservation::query()
                ->where('shuttle_schedule_id', $schedule->id)
                ->whereDate('travel_date', $date)
                ->whereBetween('seat_number', [$firstEligibleSeat, $capacity])
                ->lockForUpdate()
                ->pluck('seat_number')
                ->unique();

            if ($occupiedEligibleSeats->count() < $eligibleCapacity) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'An eligible seat is still available. Select a seat before joining the waitlist.',
                ]);
            }

            return ShuttleWaitlistEntry::query()->create([
                'employee_id' => $employee->getKey(),
                'shuttle_schedule_id' => $schedule->id,
                'travel_date' => $date->toDateString(),
                'queued_at' => now(),
            ]);
        }, 5);
    }

    public function cancel(
        Employee $employee,
        ShuttleReservation $reservation,
    ): ?ShuttleReservation {
        return DB::transaction(function () use ($employee, $reservation): ?ShuttleReservation {
            $schedule = $this->lockSchedule((int) $reservation->shuttle_schedule_id);
            $lockedReservation = ShuttleReservation::query()
                ->whereKey($reservation->getKey())
                ->where('employee_id', $employee->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedReservation === null) {
                throw ValidationException::withMessages([
                    'reservation' => 'This reservation is no longer available.',
                ]);
            }

            $date = $this->parseTravelDate($lockedReservation->travel_date->toDateString());
            $this->assertBeforeDeparture($schedule, $date);

            $releasedSeat = $lockedReservation->seat_number;
            $lockedReservation->delete();

            $capacity = $this->effectiveCapacity($schedule);

            if ($releasedSeat > $capacity) {
                return null;
            }

            $waitlistEntry = $this->nextWaitlistEntry(
                $schedule,
                $date,
                $releasedSeat <= $this->prioritySeatCount($capacity),
            );

            if ($waitlistEntry === null) {
                return null;
            }

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

            Mail::to($promotedEmployee->email)->queue(new WaitlistPromotionMail(
                employeeName: $promotedEmployee->name,
                routeName: $schedule->route->name,
                travelDate: $date->format('F j, Y'),
                departureTime: $this->departureAt($schedule, $date)->format('g:i A'),
                plateNumber: $schedule->vehicle->plate_number,
                seatNumber: $releasedSeat,
            ));

            return $promotedReservation;
        }, 5);
    }

    public function withdraw(
        Employee $employee,
        ShuttleWaitlistEntry $waitlistEntry,
    ): void {
        DB::transaction(function () use ($employee, $waitlistEntry): void {
            $schedule = $this->lockSchedule((int) $waitlistEntry->shuttle_schedule_id);
            $lockedEntry = ShuttleWaitlistEntry::query()
                ->whereKey($waitlistEntry->getKey())
                ->where('employee_id', $employee->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedEntry === null) {
                throw ValidationException::withMessages([
                    'waitlist' => 'This waitlist entry is no longer available.',
                ]);
            }

            $date = $this->parseTravelDate($lockedEntry->travel_date->toDateString());
            $this->assertBeforeDeparture($schedule, $date);
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

    private function assertReservableOccurrence(
        ShuttleSchedule $schedule,
        string $travelDate,
    ): CarbonImmutable {
        $date = $this->parseTravelDate($travelDate);
        $today = CarbonImmutable::now($this->operatingTimezone())->startOfDay();

        if ($date->lt($today) || $date->gt($today->addDays($this->bookingHorizonDays()))) {
            throw ValidationException::withMessages([
                'travel_date' => 'Choose a travel date within the available booking window.',
            ]);
        }

        if (
            $schedule->status !== 'ACTIVE'
            || $schedule->route->status !== 'ACTIVE'
            || $schedule->vehicle->status !== 'ACTIVE'
            || $schedule->driver->status !== 'ACTIVE'
        ) {
            throw ValidationException::withMessages([
                'schedule_id' => 'This shuttle schedule is not currently available for reservations.',
            ]);
        }

        if (
            $date->toDateString() < $schedule->effective_from->toDateString()
            || (
                $schedule->effective_until !== null
                && $date->toDateString() > $schedule->effective_until->toDateString()
            )
        ) {
            throw ValidationException::withMessages([
                'travel_date' => 'This schedule does not operate on the selected date.',
            ]);
        }

        $operatingDay = mb_strtolower($date->format('l'));

        if (! in_array($operatingDay, $schedule->operating_days ?? [], true)) {
            throw ValidationException::withMessages([
                'travel_date' => 'This schedule does not operate on the selected day.',
            ]);
        }

        $this->assertBeforeDeparture($schedule, $date);

        return $date;
    }

    private function assertBeforeDeparture(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
    ): void {
        if ($this->departureAt($schedule, $date)->lessThanOrEqualTo(
            CarbonImmutable::now($this->operatingTimezone())
        )) {
            throw ValidationException::withMessages([
                'travel_date' => 'Reservations and waitlist changes close at departure time.',
            ]);
        }
    }

    private function assertEmployeeHasNoEntry(
        Employee $employee,
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
    ): void {
        $reservation = ShuttleReservation::query()
            ->where('employee_id', $employee->getKey())
            ->where('shuttle_schedule_id', $schedule->id)
            ->whereDate('travel_date', $date)
            ->lockForUpdate()
            ->first(['id']);

        if ($reservation !== null) {
            throw ValidationException::withMessages([
                'schedule_id' => 'You already have a reservation for this shuttle occurrence.',
            ]);
        }

        $waitlistEntry = ShuttleWaitlistEntry::query()
            ->where('employee_id', $employee->getKey())
            ->where('shuttle_schedule_id', $schedule->id)
            ->whereDate('travel_date', $date)
            ->lockForUpdate()
            ->first(['id']);

        if ($waitlistEntry !== null) {
            throw ValidationException::withMessages([
                'schedule_id' => 'You are already on the waitlist for this shuttle occurrence.',
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
            ->with('employee:employee_id,name,email,priority_status')
            ->where('shuttle_schedule_id', $schedule->id)
            ->whereDate('travel_date', $date)
            ->orderBy('queued_at')
            ->orderBy('id')
            ->lockForUpdate();
    }

    private function effectiveCapacity(ShuttleSchedule $schedule): int
    {
        return (int) ($schedule->capacity_override ?? $schedule->vehicle->capacity);
    }

    private function prioritySeatCount(int $capacity): int
    {
        return min(
            $capacity,
            max(0, (int) config('shuttle.priority_seat_count', 8))
        );
    }

    private function departureAt(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            $date->toDateString().' '.(string) $schedule->departure_time,
            $this->operatingTimezone()
        );
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

    private function bookingHorizonDays(): int
    {
        return max(0, (int) config('shuttle.booking_horizon_days', 30));
    }
}
