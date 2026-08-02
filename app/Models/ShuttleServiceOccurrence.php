<?php

namespace App\Models;

use App\Enums\ServiceOccurrenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShuttleServiceOccurrence extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'shuttle_schedule_id',
        'travel_date',
        'route_id',
        'vehicle_id',
        'driver_id',
        'route_name',
        'origin',
        'destination',
        'direction',
        'plate_number',
        'vehicle_type',
        'driver_name',
        'driver_employee_id',
        'departure_time',
        'scheduled_departure_at',
        'effective_capacity',
        'available_capacity',
        'priority_seats',
        'unavailable_seats',
        'waitlist_enabled',
        'waitlist_capacity',
        'status',
        'opening_odometer_km',
        'closing_odometer_km',
        'distance_km',
        'actual_departure_at',
        'actual_arrival_at',
        'operational_notes',
        'incident_notes',
        'not_operated_reason',
        'reservation_count',
        'boarded_count',
        'no_show_count',
        'unserved_waitlist_count',
        'finalized_at',
        'finalized_by',
        'finalized_by_id_snapshot',
        'finalized_by_name',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => ServiceOccurrenceStatus::Scheduled->value,
        'waitlist_enabled' => true,
        'reservation_count' => 0,
        'boarded_count' => 0,
        'no_show_count' => 0,
        'unserved_waitlist_count' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'travel_date' => 'date:Y-m-d',
            'scheduled_departure_at' => 'datetime',
            'effective_capacity' => 'integer',
            'available_capacity' => 'integer',
            'priority_seats' => 'array',
            'unavailable_seats' => 'array',
            'waitlist_enabled' => 'boolean',
            'waitlist_capacity' => 'integer',
            'status' => ServiceOccurrenceStatus::class,
            'opening_odometer_km' => 'decimal:1',
            'closing_odometer_km' => 'decimal:1',
            'distance_km' => 'decimal:1',
            'actual_departure_at' => 'datetime',
            'actual_arrival_at' => 'datetime',
            'reservation_count' => 'integer',
            'boarded_count' => 'integer',
            'no_show_count' => 'integer',
            'unserved_waitlist_count' => 'integer',
            'finalized_at' => 'datetime',
            'finalized_by_id_snapshot' => 'integer',
        ];
    }

    /** @return BelongsTo<ShuttleSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ShuttleSchedule::class, 'shuttle_schedule_id');
    }

    /** @return BelongsTo<ShuttleRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(ShuttleRoute::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return BelongsTo<User, $this> */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /** @return HasMany<ShuttleServiceAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(ShuttleServiceAttendance::class);
    }

    /** @return HasMany<ShuttleServiceCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(ShuttleServiceCorrection::class);
    }

    public function isFinalized(): bool
    {
        return $this->status->isFinalized();
    }
}
