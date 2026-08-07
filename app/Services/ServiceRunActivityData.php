<?php

namespace App\Services;

use App\Enums\ServiceOccurrenceStatus;
use App\Models\ShuttleReservation;
use App\Models\ShuttleSchedule;
use App\Models\ShuttleServiceOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Shared admin-facing service trail for a schedule participant — a vehicle, route, or
 * driver. Only the current day is materialized into service occurrences, so upcoming
 * runs are projected from each schedule's recurrence rules.
 */
abstract class ServiceRunActivityData
{
    protected const HISTORY_LIMIT = 50;

    protected const UPCOMING_DAYS = 14;

    /**
     * The `shuttle_schedules` and `shuttle_service_occurrences` column holding this subject.
     */
    abstract protected function subjectColumn(): string;

    /**
     * Subject header shown at the top of the activity sheet.
     *
     * @return array{id: int, label: string, sublabel: string, status: string}
     */
    abstract protected function subjectPayload(Model $subject): array;

    /** @return array<string, mixed> */
    protected function build(Model $subject): array
    {
        $operatingTimezone = (string) config('shuttle.operating_timezone', 'Asia/Manila');
        $today = CarbonImmutable::now($operatingTimezone)->startOfDay();
        $subjectId = (int) $subject->getKey();
        $schedules = $this->schedulesFor($subjectId);
        $past = $this->past($subjectId, $today);
        $todayRuns = $this->today($subjectId, $schedules, $today, $operatingTimezone);
        $upcoming = $this->upcoming($schedules, $today, $operatingTimezone);

        return [
            'subject' => $this->subjectPayload($subject),
            'summary' => [
                'completed_services' => $this->occurrenceQuery($subjectId)
                    ->where('status', ServiceOccurrenceStatus::Completed)
                    ->count(),
                'not_operated_services' => $this->occurrenceQuery($subjectId)
                    ->where('status', ServiceOccurrenceStatus::NotOperated)
                    ->count(),
                'passengers_boarded' => (int) $this->occurrenceQuery($subjectId)->sum('boarded_count'),
                'active_schedules' => $schedules->count(),
                'today_count' => $todayRuns->count(),
                'upcoming_count' => $upcoming->count(),
            ],
            'past' => $past->all(),
            'today' => $todayRuns->all(),
            'upcoming' => $upcoming->all(),
            'today_date' => $today->toDateString(),
            'history_limit' => static::HISTORY_LIMIT,
            'upcoming_days' => static::UPCOMING_DAYS,
        ];
    }

    /** @return Builder<ShuttleServiceOccurrence> */
    private function occurrenceQuery(int $subjectId)
    {
        return ShuttleServiceOccurrence::query()->where($this->subjectColumn(), $subjectId);
    }

    /** @return Collection<int, ShuttleSchedule> */
    private function schedulesFor(int $subjectId): Collection
    {
        return ShuttleSchedule::query()
            ->with([
                'route:id,name,origin,destination,status',
                'vehicle:id,plate_number,capacity,status',
                'driver:id,name,employee_id,status',
            ])
            ->where($this->subjectColumn(), $subjectId)
            ->where('status', 'ACTIVE')
            ->orderBy('departure_time')
            ->orderBy('id')
            ->get();
    }

    /**
     * Finalized and past-dated services, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function past(int $subjectId, CarbonImmutable $today): Collection
    {
        return $this->occurrenceQuery($subjectId)
            ->whereDate('travel_date', '<', $today->toDateString())
            ->latest('scheduled_departure_at')
            ->limit(static::HISTORY_LIMIT)
            ->get()
            ->map(fn (ShuttleServiceOccurrence $occurrence): array => [
                'id' => $occurrence->getKey(),
                'travel_date' => $occurrence->travel_date->toDateString(),
                'route_name' => $occurrence->route_name,
                'origin' => $occurrence->origin,
                'destination' => $occurrence->destination,
                'direction' => $occurrence->direction,
                'departure_time' => mb_substr((string) $occurrence->departure_time, 0, 5),
                'plate_number' => $occurrence->plate_number,
                'driver_name' => $occurrence->driver_name,
                'status' => $occurrence->status->value,
                'reservation_count' => (int) $occurrence->reservation_count,
                'boarded_count' => (int) $occurrence->boarded_count,
                'no_show_count' => (int) $occurrence->no_show_count,
                'distance_km' => $occurrence->distance_km,
                'actual_departure_at' => $occurrence->actual_departure_at?->toIso8601String(),
                'actual_arrival_at' => $occurrence->actual_arrival_at?->toIso8601String(),
                'not_operated_reason' => $occurrence->not_operated_reason,
            ])
            ->values();
    }

    /**
     * Today's runs, preferring the materialized occurrence and falling back to the
     * schedule projection when the day has not been materialized yet.
     *
     * @param  Collection<int, ShuttleSchedule>  $schedules
     * @return Collection<int, array<string, mixed>>
     */
    private function today(
        int $subjectId,
        Collection $schedules,
        CarbonImmutable $today,
        string $operatingTimezone,
    ): Collection {
        $occurrences = $this->occurrenceQuery($subjectId)
            ->whereDate('travel_date', $today->toDateString())
            ->get();
        $materializedScheduleIds = $occurrences
            ->pluck('shuttle_schedule_id')
            ->filter()
            ->all();
        $now = CarbonImmutable::now($operatingTimezone);

        $fromOccurrences = $occurrences->map(function (ShuttleServiceOccurrence $occurrence) use ($now): array {
            $departureAt = $occurrence->scheduled_departure_at->toImmutable();

            return [
                'key' => 'occurrence-'.$occurrence->getKey(),
                'occurrence_id' => $occurrence->getKey(),
                'schedule_id' => $occurrence->shuttle_schedule_id,
                'travel_date' => $occurrence->travel_date->toDateString(),
                'route_name' => $occurrence->route_name,
                'origin' => $occurrence->origin,
                'destination' => $occurrence->destination,
                'direction' => $occurrence->direction,
                'departure_time' => mb_substr((string) $occurrence->departure_time, 0, 5),
                'scheduled_departure_at' => $departureAt->toIso8601String(),
                'plate_number' => $occurrence->plate_number,
                'driver_name' => $occurrence->driver_name,
                'status' => $occurrence->status->value,
                'reservation_count' => (int) $occurrence->reservation_count,
                'boarded_count' => (int) $occurrence->boarded_count,
                'effective_capacity' => (int) $occurrence->effective_capacity,
                'has_departed' => $departureAt->lessThanOrEqualTo($now),
            ];
        });

        $reservationCounts = $this->reservationCounts(
            $schedules->pluck('id')->all(),
            $today,
            $today
        );
        $fromSchedules = $schedules
            ->reject(fn (ShuttleSchedule $schedule): bool => in_array($schedule->getKey(), $materializedScheduleIds, true))
            ->filter(fn (ShuttleSchedule $schedule): bool => $this->operatesOn($schedule, $today))
            ->map(fn (ShuttleSchedule $schedule): array => $this->projectedRun(
                $schedule,
                $today,
                $reservationCounts,
                $operatingTimezone
            ));

        return $fromOccurrences
            ->concat($fromSchedules)
            ->sortBy('scheduled_departure_at')
            ->values();
    }

    /**
     * Runs projected from schedule recurrence for the days after today.
     *
     * @param  Collection<int, ShuttleSchedule>  $schedules
     * @return Collection<int, array<string, mixed>>
     */
    private function upcoming(
        Collection $schedules,
        CarbonImmutable $today,
        string $operatingTimezone,
    ): Collection {
        if ($schedules->isEmpty()) {
            return collect();
        }

        $reservationCounts = $this->reservationCounts(
            $schedules->pluck('id')->all(),
            $today->addDay(),
            $today->addDays(static::UPCOMING_DAYS)
        );
        $runs = collect();

        for ($offset = 1; $offset <= static::UPCOMING_DAYS; $offset++) {
            $date = $today->addDays($offset);

            foreach ($schedules as $schedule) {
                if (! $this->operatesOn($schedule, $date)) {
                    continue;
                }

                $runs->push($this->projectedRun(
                    $schedule,
                    $date,
                    $reservationCounts,
                    $operatingTimezone
                ));
            }
        }

        return $runs->sortBy('scheduled_departure_at')->values();
    }

    /**
     * @param  array<string, int>  $reservationCounts
     * @return array<string, mixed>
     */
    private function projectedRun(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        array $reservationCounts,
        string $operatingTimezone,
    ): array {
        $travelDate = $date->toDateString();
        $departureTime = mb_substr((string) $schedule->departure_time, 0, 5);
        $departureAt = CarbonImmutable::parse(
            $travelDate.' '.$departureTime,
            $operatingTimezone
        );

        return [
            'key' => 'schedule-'.$schedule->getKey().'-'.$travelDate,
            'occurrence_id' => null,
            'schedule_id' => $schedule->getKey(),
            'travel_date' => $travelDate,
            'route_name' => $schedule->route?->name,
            'origin' => $schedule->route?->origin,
            'destination' => $schedule->route?->destination,
            'direction' => $schedule->direction,
            'departure_time' => $departureTime,
            'scheduled_departure_at' => $departureAt->toIso8601String(),
            'plate_number' => $schedule->vehicle?->plate_number,
            'driver_name' => $schedule->driver?->name,
            'status' => null,
            'reservation_count' => $reservationCounts[$schedule->getKey().'|'.$travelDate] ?? 0,
            'boarded_count' => 0,
            'effective_capacity' => $schedule->capacity_override ?? (int) $schedule->vehicle?->capacity,
            'has_departed' => $departureAt->lessThanOrEqualTo(CarbonImmutable::now($operatingTimezone)),
        ];
    }

    /**
     * Booked seats per schedule and travel date.
     *
     * @param  list<int>  $scheduleIds
     * @return array<string, int>
     */
    private function reservationCounts(
        array $scheduleIds,
        CarbonImmutable $from,
        CarbonImmutable $until,
    ): array {
        if ($scheduleIds === []) {
            return [];
        }

        return ShuttleReservation::query()
            ->selectRaw('shuttle_schedule_id, travel_date, COUNT(*) as reserved')
            ->whereIn('shuttle_schedule_id', $scheduleIds)
            ->whereDate('travel_date', '>=', $from->toDateString())
            ->whereDate('travel_date', '<=', $until->toDateString())
            ->groupBy('shuttle_schedule_id', 'travel_date')
            ->get()
            ->mapWithKeys(fn (ShuttleReservation $row): array => [
                $row->shuttle_schedule_id.'|'.$row->travel_date->toDateString() => (int) $row->reserved,
            ])
            ->all();
    }

    private function operatesOn(ShuttleSchedule $schedule, CarbonImmutable $date): bool
    {
        if ($date->lt($schedule->effective_from->toImmutable()->startOfDay())) {
            return false;
        }

        if (
            $schedule->effective_until !== null
            && $date->gt($schedule->effective_until->toImmutable()->startOfDay())
        ) {
            return false;
        }

        return in_array(
            mb_strtolower($date->format('l')),
            $schedule->operating_days ?? [],
            true
        );
    }
}
