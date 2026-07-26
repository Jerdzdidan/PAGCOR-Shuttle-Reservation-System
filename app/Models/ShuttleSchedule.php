<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ShuttleSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\ShuttleScheduleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'route_id', 'vehicle_id', 'driver_id', 'direction', 'departure_time', 'operating_days',
        'effective_from', 'effective_until', 'capacity_override', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'operating_days' => 'array',
            'effective_from' => 'date:Y-m-d',
            'effective_until' => 'date:Y-m-d',
            'capacity_override' => 'integer',
        ];
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

    /** @return HasMany<ShuttleReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(ShuttleReservation::class);
    }

    /** @return HasMany<ShuttleWaitlistEntry, $this> */
    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(ShuttleWaitlistEntry::class);
    }
}
