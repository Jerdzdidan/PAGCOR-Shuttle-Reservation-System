<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['plate_number', 'vehicle_type', 'capacity', 'status', 'notes', 'current_odometer_km'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'ACTIVE'];

    /** @return HasMany<ShuttleSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(ShuttleSchedule::class);
    }

    /** @return HasMany<ShuttleServiceOccurrence, $this> */
    public function serviceOccurrences(): HasMany
    {
        return $this->hasMany(ShuttleServiceOccurrence::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'current_odometer_km' => 'decimal:1',
        ];
    }
}
