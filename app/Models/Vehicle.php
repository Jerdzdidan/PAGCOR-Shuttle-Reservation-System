<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['plate_number', 'vehicle_type', 'capacity', 'status', 'notes'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'ACTIVE'];

    /** @return HasMany<ShuttleSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(ShuttleSchedule::class);
    }
}
