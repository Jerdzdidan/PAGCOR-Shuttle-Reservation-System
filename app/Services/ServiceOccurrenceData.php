<?php

namespace App\Services;

use App\Models\ShuttleReservation;
use App\Models\ShuttleServiceAttendance;
use App\Models\ShuttleServiceOccurrence;

class ServiceOccurrenceData
{
    /** @return array<string, mixed> */
    public function summary(ShuttleServiceOccurrence $occurrence): array
    {
        $finalizerName = $occurrence->finalized_by_name
            ?? (
                $occurrence->relationLoaded('finalizer')
                    ? $occurrence->finalizer?->name
                    : null
            );
        $finalizer = $finalizerName === null
            ? null
            : [
                'id' => $occurrence->finalized_by_id_snapshot
                    ?? $occurrence->finalized_by,
                'name' => $finalizerName,
            ];

        return [
            'id' => $occurrence->getKey(),
            'schedule_id' => $occurrence->shuttle_schedule_id,
            'shuttle_schedule_id' => $occurrence->shuttle_schedule_id,
            'travel_date' => $occurrence->travel_date->toDateString(),
            'status' => $occurrence->status->value,
            'route_name' => $occurrence->route_name,
            'origin' => $occurrence->origin,
            'destination' => $occurrence->destination,
            'route_origin' => $occurrence->origin,
            'route_destination' => $occurrence->destination,
            'route' => [
                'id' => $occurrence->route_id,
                'name' => $occurrence->route_name,
                'origin' => $occurrence->origin,
                'destination' => $occurrence->destination,
            ],
            'direction' => $occurrence->direction,
            'plate_number' => $occurrence->plate_number,
            'vehicle' => [
                'id' => $occurrence->vehicle_id,
                'plate_number' => $occurrence->plate_number,
                'vehicle_type' => $occurrence->vehicle_type,
            ],
            'driver_name' => $occurrence->driver_name,
            'driver' => [
                'id' => $occurrence->driver_id,
                'name' => $occurrence->driver_name,
                'employee_id' => $occurrence->driver_employee_id,
            ],
            'departure_time' => mb_substr((string) $occurrence->departure_time, 0, 5),
            'scheduled_departure_at' => $occurrence->scheduled_departure_at->toIso8601String(),
            'effective_capacity' => $occurrence->effective_capacity,
            'available_capacity' => $occurrence->available_capacity,
            'priority_seats' => $occurrence->priority_seats ?? [],
            'unavailable_seats' => $occurrence->unavailable_seats ?? [],
            'waitlist_enabled' => $occurrence->waitlist_enabled,
            'waitlist_capacity' => $occurrence->waitlist_capacity,
            'opening_odometer_km' => $occurrence->opening_odometer_km,
            'closing_odometer_km' => $occurrence->closing_odometer_km,
            'distance_km' => $occurrence->distance_km,
            'trip_distance_km' => $occurrence->distance_km,
            'actual_departure_at' => $occurrence->actual_departure_at?->toIso8601String(),
            'actual_arrival_at' => $occurrence->actual_arrival_at?->toIso8601String(),
            'operational_notes' => $occurrence->operational_notes,
            'incident_notes' => $occurrence->incident_notes,
            'not_operated_reason' => $occurrence->not_operated_reason,
            'reservation_count' => $occurrence->reservation_count,
            'reserved_count' => $occurrence->reservation_count,
            'boarded_count' => $occurrence->boarded_count,
            'no_show_count' => $occurrence->no_show_count,
            'unserved_waitlist_count' => $occurrence->unserved_waitlist_count,
            'waitlist_unserved_count' => $occurrence->unserved_waitlist_count,
            'finalized_at' => $occurrence->finalized_at?->toIso8601String(),
            'finalizer' => $finalizer,
            'finalized_by' => $finalizer,
            'finalized_by_name' => $finalizerName,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(ShuttleServiceOccurrence $occurrence): array
    {
        $occurrence->loadMissing([
            'finalizer:id,name',
            'corrections' => fn ($query) => $query
                ->with('administrator:id,name')
                ->latest('corrected_at'),
        ]);

        $reservations = ShuttleReservation::query()
            ->with('employee:employee_id,employee_code,name,email,contact_number,department,position,priority_status,status')
            ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
            ->whereDate('travel_date', $occurrence->travel_date)
            ->orderBy('seat_number')
            ->get();
        $attendances = ShuttleServiceAttendance::query()
            ->with('recorder:id,name')
            ->where('shuttle_service_occurrence_id', $occurrence->getKey())
            ->orderBy('seat_number')
            ->get();
        $attendanceByEmployee = $attendances->keyBy('employee_id_snapshot');

        return [
            ...$this->summary($occurrence),
            'reservations' => $reservations
                ->map(function (ShuttleReservation $reservation) use ($attendanceByEmployee): array {
                    $attendance = $attendanceByEmployee->get($reservation->employee_id);

                    return [
                        'id' => $reservation->getKey(),
                        'employee_id' => $reservation->employee_id,
                        'seat_number' => $reservation->seat_number,
                        'source' => $reservation->source,
                        'reserved_at' => $reservation->reserved_at->toIso8601String(),
                        'employee' => [
                            'employee_id' => $reservation->employee->getKey(),
                            'employee_code' => $reservation->employee->employee_code,
                            'name' => $reservation->employee->name,
                            'email' => $reservation->employee->email,
                            'contact_number' => $reservation->employee->contact_number,
                            'department' => $reservation->employee->department,
                            'position' => $reservation->employee->position,
                            'priority_status' => $reservation->employee->priority_status,
                            'status' => $reservation->employee->status,
                        ],
                        'attendance' => $attendance === null
                            ? null
                            : $this->attendanceItem($attendance),
                    ];
                })
                ->values()
                ->all(),
            'attendances' => $attendances
                ->map(fn (ShuttleServiceAttendance $attendance): array => $this->attendanceItem($attendance))
                ->values()
                ->all(),
            'corrections' => $occurrence->corrections
                ->map(function ($correction): array {
                    $administratorName = $correction->corrected_by_name
                        ?? $correction->administrator?->name;

                    return [
                        'id' => $correction->getKey(),
                        'action' => $correction->action,
                        'reason' => $correction->reason,
                        'before_values' => $correction->before_values,
                        'after_values' => $correction->after_values,
                        'corrected_at' => $correction->corrected_at->toIso8601String(),
                        'administrator' => $administratorName === null
                            ? null
                            : [
                                'id' => $correction->corrected_by_id_snapshot
                                    ?? $correction->corrected_by,
                                'name' => $administratorName,
                            ],
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function attendanceItem(
        ShuttleServiceAttendance $attendance,
    ): array {
        $recorderName = $attendance->recorded_by_name
            ?? $attendance->recorder?->name;

        return [
            'id' => $attendance->getKey(),
            'reservation_id' => $attendance->shuttle_reservation_id,
            'employee_id' => $attendance->employee_id_snapshot,
            'employee_code' => $attendance->employee_code_snapshot,
            'employee_name' => $attendance->employee_name,
            'department' => $attendance->department,
            'priority_status' => $attendance->priority_status,
            'seat_number' => $attendance->seat_number,
            'status' => $attendance->status->value,
            'recording_method' => $attendance->recording_method->value,
            'boarded_at' => $attendance->boarded_at?->toIso8601String(),
            'recorded_by' => $recorderName === null
                ? null
                : [
                    'id' => $attendance->recorded_by_id_snapshot
                        ?? $attendance->recorded_by,
                    'name' => $recorderName,
                ],
        ];
    }
}
