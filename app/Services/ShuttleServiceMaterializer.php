<?php

namespace App\Services;

use App\Enums\ServiceOccurrenceStatus;
use App\Models\ShuttleReservation;
use App\Models\ShuttleSchedule;
use App\Models\ShuttleServiceAttendance;
use App\Models\ShuttleServiceOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ShuttleServiceMaterializer
{
    public function __construct(private ShuttleSeatPolicy $seatPolicy) {}

    /**
     * @return array{created: int, transitioned: int}
     */
    public function materializeCurrentDay(): array
    {
        $now = CarbonImmutable::now($this->operatingTimezone());
        $travelDate = $now->startOfDay();
        $operatingDay = mb_strtolower($travelDate->format('l'));
        $created = 0;

        ShuttleSchedule::query()
            ->with([
                'route:id,name,origin,destination,status',
                'vehicle:id,plate_number,vehicle_type,capacity,status',
                'driver:id,name,employee_id,status',
            ])
            ->where('status', 'ACTIVE')
            ->whereDate('effective_from', '<=', $travelDate)
            ->where(function (Builder $query) use ($travelDate): void {
                $query
                    ->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $travelDate);
            })
            ->whereJsonContains('operating_days', $operatingDay)
            ->orderBy('id')
            ->chunkById(100, function ($schedules) use ($now, $travelDate, &$created): void {
                foreach ($schedules as $schedule) {
                    $departureAt = CarbonImmutable::parse(
                        $travelDate->toDateString().' '.(string) $schedule->departure_time,
                        $this->operatingTimezone()
                    );
                    $effectiveCapacity = $this->seatPolicy->effectiveCapacity($schedule);
                    $availableSeats = $this->seatPolicy->availableSeats($schedule);
                    $occurrence = ShuttleServiceOccurrence::query()->firstOrCreate(
                        [
                            'shuttle_schedule_id' => $schedule->getKey(),
                            'travel_date' => $travelDate->toDateString(),
                        ],
                        [
                            'route_id' => $schedule->route_id,
                            'vehicle_id' => $schedule->vehicle_id,
                            'driver_id' => $schedule->driver_id,
                            'route_name' => $schedule->route->name,
                            'origin' => $schedule->route->origin,
                            'destination' => $schedule->route->destination,
                            'direction' => $schedule->direction,
                            'plate_number' => $schedule->vehicle->plate_number,
                            'vehicle_type' => $schedule->vehicle->vehicle_type,
                            'driver_name' => $schedule->driver->name,
                            'driver_employee_id' => $schedule->driver->employee_id,
                            'departure_time' => $schedule->departure_time,
                            'scheduled_departure_at' => $departureAt,
                            'effective_capacity' => $effectiveCapacity,
                            'available_capacity' => count($availableSeats),
                            'priority_seats' => $this->seatPolicy->effectivePrioritySeats($schedule),
                            'unavailable_seats' => $this->seatPolicy->effectiveUnavailableSeats($schedule),
                            'waitlist_enabled' => (bool) $schedule->waitlist_enabled,
                            'waitlist_capacity' => $schedule->waitlist_capacity,
                            'status' => $departureAt->lessThanOrEqualTo($now)
                                ? ServiceOccurrenceStatus::AwaitingCompletion
                                : ServiceOccurrenceStatus::Scheduled,
                        ]
                    );

                    if ($occurrence->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        $transitioned = ShuttleServiceOccurrence::query()
            ->where('status', ServiceOccurrenceStatus::Scheduled)
            ->where('scheduled_departure_at', '<=', $now)
            ->update([
                'status' => ServiceOccurrenceStatus::AwaitingCompletion,
                'updated_at' => now(),
            ]);

        $this->synchronizeOpenCounts($travelDate);

        return [
            'created' => $created,
            'transitioned' => $transitioned,
        ];
    }

    private function synchronizeOpenCounts(CarbonImmutable $travelDate): void
    {
        $occurrences = ShuttleServiceOccurrence::query()
            ->whereDate('travel_date', $travelDate)
            ->whereIn('status', [
                ServiceOccurrenceStatus::Scheduled,
                ServiceOccurrenceStatus::AwaitingCompletion,
            ])
            ->get(['id', 'shuttle_schedule_id']);

        if ($occurrences->isEmpty()) {
            return;
        }

        $reservationCounts = ShuttleReservation::query()
            ->whereIn(
                'shuttle_schedule_id',
                $occurrences->pluck('shuttle_schedule_id')->filter()->unique()
            )
            ->whereDate('travel_date', $travelDate)
            ->selectRaw('shuttle_schedule_id, COUNT(*) as aggregate')
            ->groupBy('shuttle_schedule_id')
            ->pluck('aggregate', 'shuttle_schedule_id');
        $attendanceCounts = ShuttleServiceAttendance::query()
            ->whereIn('shuttle_service_occurrence_id', $occurrences->modelKeys())
            ->selectRaw(
                "shuttle_service_occurrence_id,
                SUM(CASE WHEN status = 'BOARDED' THEN 1 ELSE 0 END) as boarded,
                SUM(CASE WHEN status = 'NO_SHOW' THEN 1 ELSE 0 END) as no_show"
            )
            ->groupBy('shuttle_service_occurrence_id')
            ->get()
            ->keyBy('shuttle_service_occurrence_id');

        foreach ($occurrences as $occurrence) {
            $attendance = $attendanceCounts->get($occurrence->getKey());

            ShuttleServiceOccurrence::query()
                ->whereKey($occurrence->getKey())
                ->whereIn('status', [
                    ServiceOccurrenceStatus::Scheduled,
                    ServiceOccurrenceStatus::AwaitingCompletion,
                ])
                ->update([
                    'reservation_count' => (int) (
                        $reservationCounts->get($occurrence->shuttle_schedule_id) ?? 0
                    ),
                    'boarded_count' => (int) ($attendance?->boarded ?? 0),
                    'no_show_count' => (int) ($attendance?->no_show ?? 0),
                ]);
        }
    }

    private function operatingTimezone(): string
    {
        return (string) config('shuttle.operating_timezone', 'Asia/Manila');
    }
}
