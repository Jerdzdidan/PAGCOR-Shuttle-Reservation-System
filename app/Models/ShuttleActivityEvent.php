<?php

namespace App\Models;

use App\Enums\ShuttleActivityEventType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ShuttleActivityEvent extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'employee_id_snapshot',
        'employee_code_snapshot',
        'shuttle_schedule_id',
        'shuttle_schedule_id_snapshot',
        'shuttle_service_occurrence_id',
        'route_id_snapshot',
        'route_name',
        'direction',
        'departure_time',
        'vehicle_id_snapshot',
        'plate_number',
        'driver_id_snapshot',
        'driver_name',
        'driver_employee_id',
        'travel_date',
        'event_type',
        'seat_number',
        'occurred_at',
        'employee_name',
        'employee_priority_status',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Shuttle activity events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Shuttle activity events are immutable.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'employee_id_snapshot' => 'integer',
            'shuttle_schedule_id_snapshot' => 'integer',
            'route_id_snapshot' => 'integer',
            'vehicle_id_snapshot' => 'integer',
            'driver_id_snapshot' => 'integer',
            'travel_date' => 'date:Y-m-d',
            'event_type' => ShuttleActivityEventType::class,
            'seat_number' => 'integer',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        Employee $employee,
        ShuttleSchedule $schedule,
        CarbonInterface|string $travelDate,
        ShuttleActivityEventType|string $eventType,
        ?int $seatNumber = null,
        array $metadata = [],
        ?ShuttleServiceOccurrence $occurrence = null,
    ): self {
        $schedule->loadMissing([
            'route:id,name',
            'vehicle:id,plate_number,vehicle_type',
            'driver:id,name,employee_id',
        ]);
        $driver = $schedule->driver;
        $driverEmployeeId = $driver !== null
            && array_key_exists('employee_id', $driver->getAttributes())
                ? $driver->employee_id
                : $schedule->driver()->value('employee_id');

        return self::query()->create([
            'employee_id' => $employee->getKey(),
            'employee_id_snapshot' => $employee->getKey(),
            'employee_code_snapshot' => $employee->employee_code,
            'shuttle_schedule_id' => $schedule->getKey(),
            'shuttle_schedule_id_snapshot' => $occurrence?->shuttle_schedule_id
                ?? $schedule->getKey(),
            'shuttle_service_occurrence_id' => $occurrence?->getKey(),
            'route_id_snapshot' => $occurrence?->route_id
                ?? $schedule->route_id,
            'route_name' => $occurrence?->route_name
                ?? $schedule->route?->name,
            'direction' => $occurrence?->direction
                ?? $schedule->direction,
            'departure_time' => $occurrence?->departure_time
                ?? $schedule->departure_time,
            'vehicle_id_snapshot' => $occurrence?->vehicle_id
                ?? $schedule->vehicle_id,
            'plate_number' => $occurrence?->plate_number
                ?? $schedule->vehicle?->plate_number,
            'driver_id_snapshot' => $occurrence?->driver_id
                ?? $schedule->driver_id,
            'driver_name' => $occurrence?->driver_name
                ?? $driver?->name,
            'driver_employee_id' => $occurrence?->driver_employee_id
                ?? $driverEmployeeId,
            'travel_date' => $travelDate,
            'event_type' => $eventType,
            'seat_number' => $seatNumber,
            'occurred_at' => now(),
            'employee_name' => $employee->name,
            'employee_priority_status' => $employee->priority_status,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /** @return BelongsTo<ShuttleSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ShuttleSchedule::class, 'shuttle_schedule_id');
    }

    /** @return BelongsTo<ShuttleServiceOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ShuttleServiceOccurrence::class, 'shuttle_service_occurrence_id');
    }
}
