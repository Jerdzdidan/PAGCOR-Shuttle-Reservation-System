<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Models\ShuttleServiceOccurrence;
use App\Models\ShuttleWaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class DeactivateEmployeeService
{
    public function __construct(
        private EmployeeReservationService $reservationService,
    ) {}

    /**
     * @return array{reservations: int, waitlist_entries: int}
     */
    public function deactivate(Employee $employee): array
    {
        return DB::transaction(function () use ($employee): array {
            $lockedEmployee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($employee->getKey());

            if (! $lockedEmployee->isActive()) {
                return [
                    'reservations' => 0,
                    'waitlist_entries' => 0,
                ];
            }

            $now = CarbonImmutable::now($this->operatingTimezone());
            $futureReservations = $this->futureTravel(
                ShuttleReservation::query()
                    ->where('employee_id', $lockedEmployee->getKey()),
                $now,
            )
                ->orderBy('shuttle_schedule_id')
                ->orderBy('travel_date')
                ->orderBy('id')
                ->get();

            foreach ($futureReservations as $reservation) {
                $this->reservationService->cancel($lockedEmployee, $reservation);
            }

            $futureWaitlistEntries = $this->futureTravel(
                ShuttleWaitlistEntry::query()
                    ->where('employee_id', $lockedEmployee->getKey()),
                $now,
            )
                ->orderBy('shuttle_schedule_id')
                ->orderBy('travel_date')
                ->orderBy('id')
                ->get();

            foreach ($futureWaitlistEntries as $waitlistEntry) {
                $this->reservationService->withdraw($lockedEmployee, $waitlistEntry);
            }

            $lockedEmployee->update([
                'status' => Employee::STATUS_INACTIVE,
            ]);

            return [
                'reservations' => $futureReservations->count(),
                'waitlist_entries' => $futureWaitlistEntries->count(),
            ];
        }, 5);
    }

    private function futureTravel(Builder $query, CarbonImmutable $now): Builder
    {
        $travelTable = $query->getModel()->getTable();
        $occurrenceTable = (new ShuttleServiceOccurrence)->getTable();

        return $query->where(function (Builder $futureQuery) use (
            $now,
            $occurrenceTable,
            $travelTable,
        ): void {
            $futureQuery
                ->whereDate($travelTable.'.travel_date', '>', $now->toDateString())
                ->orWhere(function (Builder $sameDayQuery) use (
                    $now,
                    $occurrenceTable,
                    $travelTable,
                ): void {
                    $sameDayQuery
                        ->whereDate($travelTable.'.travel_date', $now->toDateString())
                        ->where(function (Builder $departureQuery) use (
                            $now,
                            $occurrenceTable,
                            $travelTable,
                        ): void {
                            $departureQuery
                                ->whereExists(
                                    fn (QueryBuilder $query): QueryBuilder => $this
                                        ->matchingOccurrence($query, $occurrenceTable, $travelTable)
                                        ->where('scheduled_departure_at', '>', $now->toDateTimeString())
                                )
                                ->orWhere(function (Builder $fallbackQuery) use (
                                    $now,
                                    $occurrenceTable,
                                    $travelTable,
                                ): void {
                                    $fallbackQuery
                                        ->whereNotExists(
                                            fn (QueryBuilder $query): QueryBuilder => $this
                                                ->matchingOccurrence($query, $occurrenceTable, $travelTable)
                                        )
                                        ->whereHas(
                                            'schedule',
                                            fn (Builder $scheduleQuery): Builder => $scheduleQuery
                                                ->where('departure_time', '>', $now->format('H:i:s')),
                                        );
                                });
                        });
                });
        });
    }

    private function matchingOccurrence(
        QueryBuilder $query,
        string $occurrenceTable,
        string $travelTable,
    ): QueryBuilder {
        return $query
            ->selectRaw('1')
            ->from($occurrenceTable)
            ->whereColumn(
                $occurrenceTable.'.shuttle_schedule_id',
                $travelTable.'.shuttle_schedule_id',
            )
            ->whereColumn(
                $occurrenceTable.'.travel_date',
                $travelTable.'.travel_date',
            );
    }

    private function operatingTimezone(): string
    {
        return (string) config('shuttle.operating_timezone', 'Asia/Manila');
    }
}
