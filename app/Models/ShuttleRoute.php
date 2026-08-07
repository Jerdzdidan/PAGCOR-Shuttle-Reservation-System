<?php

namespace App\Models;

use App\Models\Concerns\RecordsUserActivity;
use Database\Factories\ShuttleRouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShuttleRoute extends Model
{
    /** @use HasFactory<ShuttleRouteFactory> */
    use HasFactory, RecordsUserActivity;

    /** @var list<string> */
    protected $fillable = ['name', 'origin', 'destination', 'status'];

    /** @return HasMany<ShuttleSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(ShuttleSchedule::class, 'route_id');
    }

    /** @return HasMany<ShuttleServiceOccurrence, $this> */
    public function serviceOccurrences(): HasMany
    {
        return $this->hasMany(ShuttleServiceOccurrence::class, 'route_id');
    }
}
